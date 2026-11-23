// Experimental renderer boundary. No WordPress, SSE or LLM dependencies.
const emotions = new Set(['neutral', 'happy', 'sad', 'angry', 'surprised', 'relaxed', 'awkward', 'good_mood', 'smug']);
const expressions = { neutral: 'neutral', happy: 'smile', sad: 'sad', angry: 'angry',
  surprised: 'surprise', relaxed: 'neutral', awkward: 'neutral' };

// A mostly open resting gaze with one slow close/hold/reopen in each 8.6 s cycle.
// This clock advances with rendered animation, so hidden or sleeping avatars do not catch up.
export function relaxedPose(seconds) {
  const phase = ((seconds % 8.6) + 8.6) % 8.6;
  const smooth = value => { const t = Math.max(0, Math.min(1, value)); return t * t * (3 - 2 * t); };
  let openness = 0.96;
  if (phase >= 4.5 && phase < 4.78) openness *= 1 - smooth((phase - 4.5) / 0.28);
  else if (phase >= 4.78 && phase < 5.1) openness = 0;
  else if (phase >= 5.1 && phase < 5.6) openness *= smooth((phase - 5.1) / 0.5);
  return { eyeLOpen: openness, eyeROpen: openness, browY: 0.12, browAngle: 0, blush: 0.08 };
}

export class MeshBridge extends EventTarget {
  #generation = 0;
  #element = null;
  #speechFrame = 0;
  #cancelLoad = null;
  #restoreAdvance = null;
  #relaxedSeconds = 0;
  #facePresets;
  #overlayKey = null;
  #kanaSpeaking = false;
  #host;
  #poster;
  state = { status: 'empty', speaking: false, sleeping: false, visible: true, emotion: 'neutral' };

  constructor(host, poster, facePresets = {}) {
    super();
    this.#host = host;
    this.#poster = poster;
    this.#facePresets = facePresets;
  }

  get element() { return this.#element; }
  get generation() { return this.#generation; }

  #update(values) {
    Object.assign(this.state, values);
    this.dispatchEvent(new CustomEvent('statechange', { detail: { ...this.state, generation: this.#generation } }));
  }

  async load(source) {
    const generation = ++this.#generation;
    this.#cancelLoad?.();
    this.stopSpeaking();
    this.#restoreAdvance?.();
    this.#restoreAdvance = null;
    this.#element?.remove();
    this.#element = null;
    this.#poster.hidden = false;
    this.#update({ status: 'loading', sleeping: false, error: null });
    await customElements.whenDefined('mesh-avatar');
    if (generation !== this.#generation) return false;
    const element = document.createElement('mesh-avatar');
    element.src = source;
    element.setAttribute('label', '坐在寶箱上的芙莉蓮');
    element.hidden = !this.state.visible;
    element.paused = !this.state.visible;
    this.#element = element;
    this.#overlayKey = null;
    const loaded = element.ready;
    const cancelled = new Promise(resolve => { this.#cancelLoad = () => resolve(false); });
    this.#host.append(element);
    try {
      const completed = await Promise.race([loaded.then(() => true), cancelled]);
      if (!completed || generation !== this.#generation) return false;
      this.#cancelLoad = null;
      // Keep automatic reactions restrained; normal breathing, blinking and small idle remain.
      element.avatar.setAutoMotion(false);
      element.avatar.setLipSync({ granularity: 0.5, openness: 0.3, closeSpeed: 0.7 });
      this.#relaxedSeconds = 0;
      this.#installAnimationClock(element.avatar);
      this.#applyExpression();
      this.#poster.hidden = true;
      this.#update({ status: 'ready' });
      return true;
    } catch (error) {
      if (generation !== this.#generation) return false;
      this.#cancelLoad = null;
      element.remove();
      this.#element = null;
      this.#poster.hidden = false;
      this.#update({ status: 'error', error: String(error.message || error) });
      return false;
    }
  }

  #installAnimationClock(avatar) {
    const advance = avatar.advance.bind(avatar);
    avatar.advance = (seconds, fps = 60) => {
      const relaxed = this.state.emotion === 'relaxed' && !this.state.sleeping && this.state.visible;
      const observe = relaxed || this.#facePresets[this.state.emotion]?.eyeOverlay || this.#kanaSpeaking;
      if (!observe) return advance(seconds, fps);
      const step = duration => {
        if (relaxed) {
          this.#relaxedSeconds += duration;
          avatar.setParameters(relaxedPose(this.#relaxedSeconds));
        }
        advance(duration, fps);
        if (this.#kanaSpeaking && !avatar.getLipSyncState().active) {
          this.#kanaSpeaking = false;
          this.#update({ speaking: false });
        }
        // Painted open eyes yield to the native blink; redraw the same simulation
        // instant only when visibility changed, so closed eyes are never covered.
        if (this.#syncOverlays()) advance(0, fps);
      };
      if (seconds === 0) step(0);
      else for (let frame = 0; frame < Math.round(seconds * fps); frame++) {
        step(1 / fps);
      }
    };
    this.#restoreAdvance = () => { avatar.advance = advance; };
  }

  #syncOverlays() {
    const avatar = this.#element?.avatar;
    if (!avatar) return false;
    const names = [];
    if (!this.state.sleeping) {
      const speaking = this.state.speaking || avatar.getLipSyncState().active;
      if (this.state.emotion === 'awkward') {
        names.push('awkward-eyes');
        if (!speaking) names.push('awkward-mouth');
      } else {
        const face = this.#facePresets[this.state.emotion];
        const p = avatar.getParameters();
        if (face?.eyeOverlay && Math.min(p.eyeLOpen ?? 1, p.eyeROpen ?? 1) >= 0.75) names.push(face.eyeOverlay);
        if (face?.mouthOverlay && !speaking) names.push(face.mouthOverlay);
      }
    }
    const key = names.join('|');
    if (key === this.#overlayKey) return false;
    this.#element.overlays = names;
    this.#overlayKey = key;
    return true;
  }

  #applyExpression() {
    const avatar = this.#element?.avatar;
    if (!avatar || this.state.sleeping) return;
    // setEmotion uses subtle talking faces and expires after a line. The preview
    // picker represents a held expression, including the painted smiling-eye sprite.
    avatar.setEmotion(null, { playMotion: false });
    const face = this.#facePresets[this.state.emotion];
    avatar.setParameters(this.state.emotion === 'relaxed'
      ? relaxedPose(this.#relaxedSeconds) : this.state.emotion === 'awkward'
        ? { eyeLOpen: 0, eyeROpen: 0, browY: 0.15, browAngle: -0.15 } : face?.parameters || {});
    avatar.setExpression(face?.expression || expressions[this.state.emotion], { parameters: !face });
    this.#syncOverlays();
  }

  setEmotion(emotion) {
    if (!emotions.has(emotion)) emotion = 'neutral';
    if (['good_mood', 'smug'].includes(emotion) && !this.#facePresets[emotion]) emotion = 'neutral';
    if (emotion !== this.state.emotion) this.#relaxedSeconds = 0;
    this.#update({ emotion });
    this.#applyExpression();
  }

  startSpeaking() {
    if (!this.#element?.avatar || this.state.sleeping || !this.state.visible) return false;
    this.stopSpeaking();
    this.#element.avatar.setSpeaking(true);
    this.#update({ speaking: true });
    this.#syncOverlays();
    const started = performance.now();
    const pulse = now => {
      const level = Math.max(0, Math.sin((now - started) / 105)) * 0.55;
      this.#element?.setVoiceLevel(level);
      this.#speechFrame = requestAnimationFrame(pulse);
    };
    this.#speechFrame = requestAnimationFrame(pulse);
    return true;
  }

  stopSpeaking() {
    cancelAnimationFrame(this.#speechFrame);
    this.#speechFrame = 0;
    this.#kanaSpeaking = false;
    this.#element?.avatar?.setSpeaking(false);
    this.#element?.avatar?.stopLipSync();
    this.#element?.setVoiceLevel(0);
    this.#update({ speaking: false });
    this.#syncOverlays();
  }

  speakKana(text) {
    const avatar = this.#element?.avatar;
    if (!avatar || this.state.sleeping || !this.state.visible) return false;
    this.stopSpeaking();
    avatar.speakKana(text);
    this.#kanaSpeaking = avatar.getLipSyncState().active;
    this.#update({ speaking: this.#kanaSpeaking });
    this.#syncOverlays();
    return this.#kanaSpeaking;
  }

  sleep(sleeping) {
    const avatar = this.#element?.avatar;
    if (!avatar) return;
    this.stopSpeaking();
    avatar.setEmotion(null);
    avatar.setExpression('neutral');
    this.#element.overlays = [];
    this.#overlayKey = '';
    avatar.setAutoIdle(!sleeping);
    avatar.setAutoMotion(false);
    this.#update({ sleeping });
    avatar.setParameters(sleeping ? { eyeLOpen: 0, eyeROpen: 0, mouthOpen: 0 } : {});
    avatar.advance(0.5, 60);
    this.#element.paused = sleeping || !this.state.visible;
    if (!sleeping) this.#applyExpression();
  }

  setVisible(visible) {
    if (!visible) this.stopSpeaking();
    this.#host.hidden = !visible;
    if (this.#element) {
      this.#element.hidden = !visible;
      this.#element.paused = !visible || this.state.sleeping;
    }
    this.#update({ visible });
  }

  destroy() {
    ++this.#generation;
    this.#cancelLoad?.();
    this.#cancelLoad = null;
    this.stopSpeaking();
    this.#restoreAdvance?.();
    this.#restoreAdvance = null;
    this.#element?.remove();
    this.#element = null;
    this.#poster.hidden = false;
    this.#update({ status: 'destroyed', sleeping: false });
  }
}
