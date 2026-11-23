// Experimental renderer boundary. No WordPress, SSE or LLM dependencies.
const emotions = new Set(['neutral', 'happy', 'sad', 'angry', 'surprised', 'relaxed']);
const expressions = { neutral: 'neutral', happy: 'smile', sad: 'sad', angry: 'angry',
  surprised: 'surprise', relaxed: 'halfLidded' };

export class MeshBridge extends EventTarget {
  #generation = 0;
  #element = null;
  #speechFrame = 0;
  #cancelLoad = null;
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

  #applyExpression() {
    const avatar = this.#element?.avatar;
    if (!avatar || this.state.sleeping) return;
    // setEmotion uses subtle talking faces and expires after a line. The preview
    // picker represents a held expression, including the painted smiling-eye sprite.
    avatar.setEmotion(null, { playMotion: false });
    avatar.setExpression(expressions[this.state.emotion]);
  }

  setEmotion(emotion) {
    if (!emotions.has(emotion)) emotion = 'neutral';
    this.#update({ emotion });
    this.#applyExpression();
  }

  startSpeaking() {
    if (!this.#element?.avatar || this.state.sleeping || !this.state.visible) return false;
    this.stopSpeaking();
    this.#element.avatar.setSpeaking(true);
    this.#update({ speaking: true });
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
  }

  sleep(sleeping) {
    const avatar = this.#element?.avatar;
    if (!avatar) return;
    this.stopSpeaking();
    avatar.setEmotion(null);
    avatar.setExpression('neutral');
    avatar.setAutoIdle(!sleeping);
    avatar.setAutoMotion(false);
    avatar.setParameters(sleeping ? { eyeLOpen: 0, eyeROpen: 0, mouthOpen: 0 } : {});
    avatar.advance(0.5, 60);
    this.#element.paused = sleeping || !this.state.visible;
    this.#update({ sleeping });
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
    this.#element?.remove();
    this.#element = null;
    this.#poster.hidden = false;
    this.#update({ status: 'destroyed', sleeping: false });
  }
}
