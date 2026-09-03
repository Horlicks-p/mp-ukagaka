/**
 * Guards the /touch/give wiring that unit tests cannot reach.
 *
 * ItemCatalogTest covers the prompt builder and the history helpers in isolation,
 * so every helper can be correct while the REST controller stops calling them.
 * That is exactly the regression this file exists to catch: before this round the
 * controller already received, verified and stored the chat history and then threw
 * it away by generating the reaction through the single-prompt API. Nothing failed,
 * because nothing asserted the connection.
 *
 * These are source-level assertions, not runtime ones -- there is no WordPress
 * harness here. They prove the calls are present and correctly ordered; they do
 * not prove the request succeeds against a live provider.
 */
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "../..");

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), "utf8");
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const touchSource = read("includes/rest/class-mpu-rest-touch.php");
const itemsSource = read("includes/personality/personality-items.php");
const historySource = read("includes/chat/class-mpu-chat-history-service.php");
const chatSource = read("includes/rest/class-mpu-rest-chat.php");

// --- the gift turn must actually carry the conversation -----------------------

const toMessages = touchSource.indexOf(
  "$messages = MPU_Chat_History_Service::to_llm_messages( $history );"
);
assert(
  toMessages !== -1,
  "give_item must turn the verified history into provider messages"
);

const appendPrompt = touchSource.indexOf("'content' => $user_prompt,");
assert(
  appendPrompt > toMessages,
  "the gift prompt must be appended after the history, as the final message"
);

assert(
  /\$normalized = \$this->run_reaction\( \$user_prompt, \$personality_id, 'give', \$messages \);/.test(
    touchSource
  ),
  "give_item must hand the messages array to run_reaction"
);

assert(
  touchSource.includes("mpu_call_ai_api_with_messages("),
  "run_reaction must use the multi-turn API when messages are supplied"
);

// The single-prompt path runs through the API response cache; the multi-turn one
// does not. Falling back to it would make repeat gifts replay verbatim again.
const withMessages = touchSource.indexOf("mpu_call_ai_api_with_messages(");
const singleShot = touchSource.indexOf("$result = mpu_call_ai_api(");
assert(
  withMessages < singleShot,
  "the multi-turn branch must be taken first; the single-prompt call is the fallback"
);

// --- both callers must share one history window ------------------------------

for (const [label, source] of [
  ["touch/give", touchSource],
  ["chat/user", chatSource],
]) {
  assert(
    source.includes("MPU_Chat_History_Service::filter_orphan_assistants") ||
      source.includes("MPU_Chat_History_Service::to_llm_messages"),
    `${label} must use the shared history window, not a hand-written copy`
  );
}
assert(
  historySource.includes("public static function to_llm_messages( array $history, int $limit = 20 )"),
  "the gift window must stay at 20 messages, matching /chat/user"
);
assert(
  chatSource.includes("array_slice($normalized_history, -20)"),
  "/chat/user LLM window changed; the gift window must be changed with it"
);

// --- a one-shot reaction offers no abilities ---------------------------------

assert(
  touchSource.includes("add_filter( 'mpu_mcp_tools_for_llm', $suppress_tools, 10, 1 );"),
  "gift reactions must withhold tool definitions for the duration of the call"
);
const removeIndex = touchSource.indexOf(
  "remove_filter( 'mpu_mcp_tools_for_llm', $suppress_tools, 10 );"
);
assert(removeIndex !== -1, "the tool suppression filter must be removed again");
assert(
  /\} finally \{\s*\n\s*remove_filter\( 'mpu_mcp_tools_for_llm'/.test(touchSource),
  "the filter must be removed in a finally block, so a provider error cannot leak it"
);
assert(
  read("includes/integrations/abilities-integration.php").includes(
    "return apply_filters( 'mpu_mcp_tools_for_llm', $formatted_tools, $provider, $role, $context );"
  ),
  "mpu_get_mcp_tools_for_llm must expose the filter the gift path relies on"
);

// --- prompt assembly stays in one place --------------------------------------

assert(
  itemsSource.includes("function mpu_build_item_reaction_prompt("),
  "the item reaction prompt builder is missing"
);
assert(
  touchSource.includes("$user_prompt = mpu_build_item_reaction_prompt("),
  "give_item must assemble its prompt through the shared builder"
);
assert(
  !touchSource.includes('【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。"\n\t\t\t. \'相手の発言'),
  "the controller must not re-append its own rules block"
);

// The builder must know whether prior turns exist, or it will declare a
// jurisdiction for a conversation that is not there and invite the model to
// invent one.
assert(
  /mpu_build_item_reaction_prompt\(\s*\$item,\s*\$visitor_message,\s*\$ukagaka_name,\s*\$reaction_pool,\s*! empty\( \$messages \)\s*\)/.test(
    touchSource
  ),
  "give_item must tell the builder whether the history window is non-empty"
);

// --- the three information sources keep separate jurisdictions ---------------

assert(
  !itemsSource.includes("情報の優先順位は"),
  "a single ranking lets the visitor's words override the catalog's item identity"
);
assert(
  itemsSource.includes("{$name}がその場で観察した事実を決める。"),
  "the catalog must own what was handed over and what the character observed"
);
assert(
  itemsSource.includes("今回の【相手の発言】を優先すること。"),
  "a contradiction between this turn and an older one must resolve to this turn"
);

console.log("gift transport smoke: OK");
