/**
 * Scripted stand-in for an Ollama server.
 *
 * The plugin talks to it through the real Ollama provider (non-streaming
 * /api/chat), so every request body the provider builds is captured here and
 * can be asserted on. Replies, delays and failures are controlled by the test
 * directly, never by wall-clock luck.
 */
const http = require("node:http");

function ndjson(value) {
  return JSON.stringify(value) + "\n";
}

function startFakeOllama({ port = 0 } = {}) {
  const requests = [];
  let seq = 0;
  let delayMs = 0;
  let failStatus = 0;
  let cutStreamAfter = 0; // >0: drop the connection after this many streamed chunks
  let replyFor = (n) => `FAKE_REPLY_${n}`;
  const replyQueue = [];

  // Ollama streams NDJSON: one message chunk per line, then a done line.
  function streamReply(res, content) {
    res.setHeader("Content-Type", "application/x-ndjson");
    const half = Math.ceil(content.length / 2);
    const chunks = [content.slice(0, half), content.slice(half)].filter(Boolean);
    let sent = 0;
    const next = () => {
      if (cutStreamAfter > 0 && sent >= cutStreamAfter) {
        res.socket.destroy();
        return;
      }
      if (sent < chunks.length) {
        res.write(ndjson({ model: "fake:latest", message: { role: "assistant", content: chunks[sent] }, done: false }));
        sent += 1;
        setTimeout(next, 150);
        return;
      }
      res.end(ndjson({ model: "fake:latest", message: { role: "assistant", content: "" }, done: true, done_reason: "stop" }));
    };
    next();
  }

  const server = http.createServer((req, res) => {
    let body = "";
    req.on("data", (chunk) => { body += chunk; });
    req.on("end", () => {
      let json = null;
      try { json = body ? JSON.parse(body) : null; } catch (error) { json = null; }
      const entry = { at: Date.now(), method: req.method, url: req.url, body: json, raw: json ? undefined : body };
      requests.push(entry);

      const respond = () => {
        res.setHeader("Content-Type", "application/json");
        if (req.url.startsWith("/api/tags")) {
          res.end(JSON.stringify({ models: [{ name: "fake:latest" }] }));
          return;
        }
        if (!req.url.startsWith("/api/chat")) {
          res.statusCode = 404;
          res.end("{}");
          return;
        }
        if (failStatus) {
          res.statusCode = failStatus;
          res.end(JSON.stringify({ error: "fake failure" }));
          return;
        }
        seq += 1;
        entry.replySeq = seq;
        const content = replyQueue.length > 0 ? replyQueue.shift() : replyFor(seq);
        entry.reply = content;
        if (json && json.stream === true) {
          streamReply(res, content);
          return;
        }
        // No tool_calls, ever: the provider would otherwise enter its tool loop.
        res.end(JSON.stringify({
          model: "fake:latest",
          created_at: new Date().toISOString(),
          message: { role: "assistant", content },
          done: true,
          done_reason: "stop",
        }));
      };

      if (delayMs > 0 && req.url.startsWith("/api/chat")) {
        setTimeout(respond, delayMs);
      } else {
        respond();
      }
    });
  });

  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, "127.0.0.1", () => {
      const address = server.address();
      resolve({
        url: `http://127.0.0.1:${address.port}`,
        chatRequests: () => requests.filter((r) => r.url.startsWith("/api/chat")),
        requests: () => requests.slice(),
        reset() {
          requests.length = 0;
          replyQueue.length = 0;
          delayMs = 0;
          failStatus = 0;
          cutStreamAfter = 0;
          replyFor = (n) => `FAKE_REPLY_${n}`;
        },
        setDelay(ms) { delayMs = ms; },
        setFailure(status) { failStatus = status; },
        cutStreamAfter(chunks) { cutStreamAfter = chunks; },
        queueReply(...contents) { replyQueue.push(...contents); },
        setReplyFor(fn) { replyFor = fn; },
        stop: () => new Promise((done) => server.close(() => done())),
      });
    });
  });
}

module.exports = { startFakeOllama };
