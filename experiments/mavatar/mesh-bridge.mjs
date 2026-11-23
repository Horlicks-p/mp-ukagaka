// Experimental renderer boundary. No WordPress, SSE or LLM dependencies.
const emotions = new Set(['neutral', 'happy', 'sad', 'angry', 'surprised', 'relaxed', 'awkward']);
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
  #host;
  #poster;
  state = { status: 'empty', speaking: false, sleeping: false, visible: true, emotion: 'neutral' };

  constructor(host, poster) {
    super();
    this.#host = host;
    this.#poster = poster;
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
      if (this.state.emotion !== 'relaxed' || this.state.sleeping || !this.state.visible) return advance(seconds, fps);
      if (seconds === 0) { avatar.setParameters(relaxedPose(this.#relaxedSeconds)); return advance(0, fps); }
      for (let frame = 0; frame < Math.round(seconds * fps); frame++) {
        this.#relaxedSeconds += 1 / fps;
        avatar.setParameters(relaxedPose(this.#relaxedSeconds));
        advance(1 / fps, fps);
      }
    };
    this.#restoreAdvance = () => { avatar.advance = advance; };
  }

  #syncOverlays() {
    if (!this.#element?.avatar) return;
    this.#element.overlays = this.state.emotion === 'awkward' && !this.state.sleeping
      ? this.state.speaking ? ['awkward-eyes'] : ['awkward-eyes', 'awkward-mouth'] : [];
  }

  #applyExpression() {
    const avatar = this.#element?.avatar;
    if (!avatar || this.state.sleeping) return;
    // setEmotion uses subtle talking faces and expires after a line. The preview
    // picker represents a held expression, including the painted smiling-eye sprite.
    avatar.setEmotion(null, { playMotion: false });
    avatar.setParameters(this.state.emotion === 'relaxed'
      ? relaxedPose(this.#relaxedSeconds) : this.state.emotion === 'awkward'
        ? { eyeLOpen: 0, eyeROpen: 0, browY: 0.15, browAngle: -0.15 } : {});
    avatar.setExpression(expressions[this.state.emotion]);
    this.#syncOverlays();
  }

  setEmotion(emotion) {
    if (!emotions.has(emotion)) emotion = 'neutral';
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
    this.#element?.avatar?.setSpeaking(false);
    this.#element?.avatar?.stopLipSync();
    this.#element?.setVoiceLevel(0);
    this.#update({ speaking: false });
    this.#syncOverlays();
  }

  sleep(sleeping) {
    const avatar = this.#element?.avatar;
    if (!avatar) return;
    this.stopSpeaking();
    avatar.setEmotion(null);
    avatar.setExpression('neutral');
    this.#element.overlays = [];
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
