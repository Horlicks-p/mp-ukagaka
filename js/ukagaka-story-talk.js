/**
 * Handwritten story player. Phase 1 supplies the engine only; UI entrypoints
 * and event listeners are connected in Phase 2. No scheduler or LLM requests.
 */
function mpuStoryBagKey(restUrl, personality) {
    const url = new URL(restUrl);
    url.hostname = url.hostname.toLowerCase();
    const site = url.href.replace(/\/+$/, "");
    let hash = 2166136261;
    for (let i = 0; i < site.length; i++) {
        hash = Math.imul(hash ^ site.charCodeAt(i), 16777619);
    }
    return `mpu_story_bag_v1_${(hash >>> 0).toString(16).padStart(8, "0")}_${personality}`;
}

function mpuCreateStoryTalk(overrides = {}) {
    const env = Object.assign({
        now: () => Date.now(),
        rng: () => Math.random(),
        storage: () => window.localStorage,
        restUrl: () => mpuRestUrl,
        generation: () => window.mpuCharacterGeneration,
        canStart: () => !document.hidden && mpuAutoTalk && !window.mpuChatModeActive
            && !mpuMessageBlocking && !mpuAiContextInProgress && !mpuGreetInProgress
            && !mpuOllamaRequesting && !mpuIsInteractionDialogActive()
            && !mpu_isUnawokenSleepMode() && !mpuIsContextPending()
            && !jQuery("#ukagaka").is(":hidden")
            && !(window.mpuRobotHideRequested && jQuery("#ukagaka").is(":animated")),
        canContinue: () => !document.hidden && mpuAutoTalk && !window.mpuChatModeActive,
        acquire: () => mpuAcquireMessageBlock("story-talk"),
        release: () => mpuReleaseMessageBlock("story-talk"),
        ownsBlock: () => mpuHasMessageBlock("story-talk"),
        runId: () => mpuGetTypewriterRunId(),
        speed: () => mpuTypewriterSpeed,
        typewrite: (text, options) => mpu_typewriter(text, "#ukagaka_msg", null, options),
        cancelTypewriter: () => mpu_cancelTypewriter(),
        emoji: (emoji) => mpuDisplayDialogueEmoji(emoji),
        show: () => mpu_showmsg(400),
        showText: () => mpu_showMsgText(),
        hide: () => mpu_hidemsg(600),
        afterFade: (callback) => jQuery("#ukagaka_msgbox").promise().done(callback),
        appendHistory: (text, timestamp) => {
            window.mpuChatHistory.push(
                { role: "user", content: "（独り言）", type: "synthetic", timestamp },
                { role: "assistant", content: text, type: "auto_talk", timestamp }
            );
            mpu_saveChatHistory();
        },
        setTimeout: (callback, ms) => setTimeout(callback, ms),
        clearTimeout: (timer) => clearTimeout(timer),
        reportError: (error) => {
            if (typeof mpu_handle_error === "function") mpu_handle_error(error, "Story Talk", { showToUser: false });
        },
    }, overrides);
    const memory = new Map();
    const memoryOnly = new Set();
    let current = null;

    function readBag(key) {
        if (memoryOnly.has(key)) return memory.get(key) || emptyBag();
        let saved;
        try {
            saved = env.storage().getItem(key);
        } catch (_) {
            // Access failures stay in memory for this page; a later successful
            // read of stale storage must not undo the in-memory progress.
            memoryOnly.add(key);
            return memory.get(key) || emptyBag();
        }
        try {
            const raw = JSON.parse(saved);
            if (!raw || raw.v !== 1 || !Array.isArray(raw.drawn)
                || raw.drawn.some((id) => typeof id !== "string")
                || !(raw.lastDrawn === null || typeof raw.lastDrawn === "string")
                || !Number.isSafeInteger(raw.cooldownUntil) || raw.cooldownUntil < 0) return emptyBag();
            memory.set(key, raw);
            return raw;
        } catch (_) {
            // Corrupt data is not a storage access failure. The next draw can
            // overwrite it with a valid bag and a persistent cooldown.
            return emptyBag();
        }
    }

    function emptyBag() {
        return { v: 1, drawn: [], lastDrawn: null, cooldownUntil: 0 };
    }

    function writeBag(key, bag) {
        memory.set(key, bag);
        if (memoryOnly.has(key)) return;
        try {
            env.storage().setItem(key, JSON.stringify(bag));
        } catch (_) {
            memoryOnly.add(key);
        }
    }

    function validCatalog(data) {
        if (!data || typeof data.personality !== "string" || !/^[a-zA-Z0-9_-]+$/.test(data.personality)
            || !data.config || !data.config.step_pause || !Array.isArray(data.stories) || !data.stories.length) return false;
        const c = data.config;
        const p = c.step_pause;
        return Number.isFinite(c.start_chance) && c.start_chance >= 0 && c.start_chance <= 1
            && Number.isFinite(c.cooldown_minutes) && c.cooldown_minutes >= 0 && c.cooldown_minutes <= 1440
            && Object.values(p).every((v) => Number.isInteger(v) && v >= 0 && v <= 10000)
            && ["base_ms", "per_char_ms", "min_ms", "max_ms"].every((key) => Number.isInteger(p[key]))
            && p.min_ms <= p.max_ms
            && new Set(data.stories.map((s) => s && s.id)).size === data.stories.length
            && data.stories.every((s) => s && /^[a-z][a-z0-9_-]{0,63}$/.test(s.id)
                && Array.isArray(s.steps) && s.steps.length >= 2 && s.steps.length <= 5
                && s.steps.every((step) => step && typeof step.text === "string" && step.text.trim()
                    && Array.from(step.text).length <= 120 && !/[<>&]/.test(step.text)
                    && (step.emoji === null || /^[a-zA-Z0-9_-]+\.png$/.test(step.emoji))));
    }

    function pause(text, config) {
        const p = config.step_pause;
        return Math.min(p.max_ms, Math.max(p.min_ms, p.base_ms + p.per_char_ms * Array.from(text).length));
    }

    function schedule(session, callback, ms) {
        const timer = env.setTimeout(() => {
            session.timers.delete(timer);
            if (session !== current || session.finished) return;
            try { callback(); } catch (error) { failSession(session, error); }
        }, ms);
        session.timers.add(timer);
        return timer;
    }

    function finish(session) {
        if (session !== current || session.finished) return;
        session.finished = true;
        for (const timer of session.timers) env.clearTimeout(timer);
        session.timers.clear();
        current = null;
        // Release first; even a storage exception must never leave a message lock.
        env.release();
        const bag = readBag(session.bagKey);
        bag.cooldownUntil = Math.floor(env.now() + session.config.cooldown_minutes * 60000);
        writeBag(session.bagKey, bag);
    }

    function cancelSession(session) {
        if (session !== current || session.finished) return;
        try {
            if (session.runId !== null && session.runId === env.runId()
                && !session.completedSteps.has(session.step)) env.cancelTypewriter();
        } finally { finish(session); }
    }

    function failSession(session, error) {
        try { cancelSession(session); } finally {
            // Use the existing error path, without a new visible message or log key.
            try { env.reportError(error); } catch (_) { /* Logging must not break recovery. */ }
        }
    }

    function alive(session) {
        if (session !== current || session.finished) return false;
        if (session.generation !== env.generation() || !env.canContinue() || !env.ownsBlock()
            || (session.runId !== null && session.runId !== env.runId())) {
            cancelSession(session);
            return false;
        }
        return true;
    }

    function stepDone(session, index) {
        if (session !== current || session.finished || session.completedSteps.has(index) || !alive(session)) return;
        session.completedSteps.add(index);
        env.clearTimeout(session.watchdog);
        session.timers.delete(session.watchdog);
        env.appendHistory(session.story.steps[index].text, env.now());
        schedule(session, () => {
            if (!alive(session)) return;
            if (index + 1 < session.story.steps.length) playStep(session, index + 1);
            else finish(session);
        }, pause(session.story.steps[index].text, session.config));
    }

    function playStep(session, index) {
        if (!alive(session)) return;
        session.step = index;
        const step = session.story.steps[index];
        env.showText();
        env.emoji(step.emoji);
        session.runId = env.typewrite(step.text, {
            skipCharacterAnimation: index > 0,
            onComplete: () => {
                try { stepDone(session, index); } catch (error) { failSession(session, error); }
            },
        });
        const runId = session.runId;
        session.watchdog = schedule(session, () => {
            if (session.step === index && session.runId === runId && !session.completedSteps.has(index)) cancelSession(session);
        }, step.text.length * session.speed + 10000);
        if (index === 0) env.show();
    }

    function tryStart(store) {
        if (current || !store || store.storiesGeneration !== env.generation() || !env.canStart()
            || !validCatalog(store.stories)) return false;
        const data = store.stories;
        let key;
        try { key = mpuStoryBagKey(env.restUrl(), data.personality); } catch (_) { return false; }
        const bag = readBag(key);
        const ids = data.stories.map((story) => story.id);
        bag.drawn = [...new Set(bag.drawn.filter((id) => ids.includes(id)))];
        if (env.now() < bag.cooldownUntil || env.rng() >= data.config.start_chance) return false;
        let available = data.stories.filter((story) => !bag.drawn.includes(story.id));
        if (!available.length) {
            bag.drawn = [];
            available = data.stories.filter((story) => ids.length === 1 || story.id !== bag.lastDrawn);
        }
        const selected = available[Math.floor(env.rng() * available.length)];
        bag.drawn.push(selected.id);
        bag.lastDrawn = selected.id;
        bag.cooldownUntil = Math.floor(env.now() + data.config.cooldown_minutes * 60000);
        writeBag(key, bag);
        const session = {
            story: JSON.parse(JSON.stringify(selected)),
            config: JSON.parse(JSON.stringify(data.config)),
            bagKey: key, generation: env.generation(), step: 0, runId: null,
            finished: false, completedSteps: new Set(), timers: new Set(),
            watchdog: null, speed: env.speed(),
        };
        current = session;
        try {
            env.acquire();
            const budget = 1000 + session.story.steps.reduce((sum, step) =>
                sum + step.text.length * session.speed + 10000 + pause(step.text, session.config), 0);
            schedule(session, () => cancelSession(session), budget);
            env.hide();
            schedule(session, () => env.afterFade(() => {
                if (!alive(session)) return;
                try { playStep(session, 0); } catch (error) { failSession(session, error); }
            }), 400);
        } catch (error) { failSession(session, error); }
        return true;
    }

    return {
        tryStart,
        isPlaying: () => current !== null,
        cancel: () => { if (current) cancelSession(current); },
        getStateForDebug: () => current ? {
            storyId: current.story.id, step: current.step, generation: current.generation,
            bagKey: current.bagKey, completedSteps: [...current.completedSteps],
        } : null,
    };
}
