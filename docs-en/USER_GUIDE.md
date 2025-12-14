# MP Ukagaka User Guide

> 🎭 Add cute Ukagaka (Desktop Mascots) to your WordPress site

---

## 📑 Table of Contents

1. [Introduction](#introduction)
2. [Installation & Activation](#installation--activation)
3. [Quick Start](#quick-start)
4. [Basic Settings](#basic-settings)
5. [Ukagaka Management](#ukagaka-management)
6. [Dialogue Settings](#dialogue-settings)
7. [AI Feature Settings](#ai-feature-settings)
8. [LLM Feature Settings (BETA)](#llm-feature-settings-beta)
9. [Extensions](#extensions)
10. [FAQ](#faq)

---

## Introduction

MP Ukagaka is a WordPress plugin that lets you display interactive desktop mascot characters (Ukagaka/Nanika) on your website. Characters can display custom dialogue messages and support AI intelligent page awareness, automatically generating comments based on article content.

### Key Features

- 🎨 **Multi-Character Support**: Create and manage multiple Ukagaka characters.
- 💬 **Custom Dialogue**: Set exclusive dialogue content for each character.
- 🤖 **AI Page Awareness**: Supports Gemini, OpenAI, and Claude AI services.
- 🌍 **Multi-Language**: Supports Traditional Chinese, Japanese, and English.
- 📁 **External Dialogue Files**: Supports TXT and JSON format dialogue files.
- ⚙️ **Highly Customizable**: Typing speed, display position, styles, and more are adjustable.

---

## Installation & Activation

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Modern browser with JavaScript support

### Installation Steps

1. Download the plugin ZIP file.
2. Log in to WordPress Admin → **Plugins** → **Add New**.
3. Click "Upload Plugin" and select the downloaded ZIP file.
4. Click "Install Now".
5. After installation, click "Activate Plugin".

### Initial Setup

After activation, go to **Settings** → **MP Ukagaka** to configure.

---

## Quick Start

### 5-Minute Quick Setup

1. **Go to Settings Page**

   - WordPress Admin → Settings → MP Ukagaka

2. **Confirm Default Ukagaka**

   - In the "General Settings" page, ensure "Default Ukagaka" is selected.
   - Check "Default Show Ukagaka" and "Default Show Balloon".

3. **Save Settings**

   - Click the "Save" button.

4. **Check the Result**
   - Go to your website's front end; you should see the Ukagaka character in the bottom right corner of the page.

---

## Basic Settings

Go to **Settings** → **MP Ukagaka** → **General Settings**

### Display Settings

| Setting Item | Description |
| -------------- | -------------------- |
| Default Ukagaka | Select the character to display by default. |
| Default Show Ukagaka | Whether to show the character image by default. |
| Default Show Balloon | Whether to show the dialogue balloon by default. |

### Dialogue Settings

| Setting Item | Description |
| -------- | ---------------------------------------- |
| Default Session | "Random Talk" or "First Talk". |
| Session Order | Whether the next dialogue upon clicking is "Sequential" or "Random". |
| Click Ukagaka | Action when clicking the character (Show next dialogue or No action). |

### Auto Dialogue

| Setting Item | Description |
| ---------------- | ---------------------------------- |
| Enable Auto Dialogue | Whether to automatically rotate dialogue. |
| Auto Dialogue Interval | Interval for automatic dialogue change (3-30 seconds). |
| Typing Effect Speed | Dialogue typing animation speed (10-200 ms/char). |

### External Dialogue Files

| Setting Item | Description |
| ---------------- | -------------------------------- |
| External File Format | Select TXT or JSON format. |
| Use External File | Whether to read dialogue from the `dialogs/` folder. |

### Page Exclusion

In the "Don't show Ukagaka on these pages" text box, enter the URLs of pages where you don't want the Ukagaka to appear, one per line.

Supports wildcard matching: add `(*)` at the end of the URL to match all subpages.

**Example:**

```
/admin/
/wp-admin/(*)
/private-page/
```

---

## Ukagaka Management

### View Existing Ukagaka

Go to **Settings** → **MP Ukagaka** → **Ukagaka**

On this page, you can:

- View all created Ukagaka.
- Edit Ukagaka name, image, and dialogue.
- Delete non-default Ukagaka.
- Set visibility (via checkbox).

### Create New Ukagaka

Go to **Settings** → **MP Ukagaka** → **Create New Ukagaka**

#### Required Fields

| Field | Description | Example |
| ---- | ------------------ | --------------------------------- |
| Name | Name of the Ukagaka | `Frieren` |
| Image URL | Full URL of the Ukagaka image | `https://example.com/ukagaka.png` |
| Dialogue | Dialogue content, one per line | See example below |

#### Dialogue Content Example

```
Welcome to my website!
The weather is nice today~
Want to read the latest article?
Magic requires time to study slowly.
```

#### Optional Fields

| Field | Description |
| ------------ | -------------------------------- |
| Dialogue Filename | Name of the external dialogue file (without extension). |
| Generate File | Checking this will automatically generate the corresponding dialogue file. |

### External Dialogue File Format

#### TXT Format

File path: `wp-content/plugins/mp-ukagaka/dialogs/CharacterName.txt`

```
First dialogue

Second dialogue

Third dialogue
```

> ⚠️ Separate each dialogue entry with an **empty line**.

#### JSON Format

File path: `wp-content/plugins/mp-ukagaka/dialogs/CharacterName.json`

```json
{
  "messages": ["First dialogue", "Second dialogue", "Third dialogue"]
}
```

---

## Dialogue Settings

Go to **Settings** → **MP Ukagaka** → **Dialogue**

### Fixed Information

This message will be **appended to the end of every dialogue**.

**Use Cases:**

- Display website announcements.
- Add signature or slogan.

**Example:**

```
—— Welcome to subscribe to our RSS
```

### General Dialogue

If filled, **all Ukagaka will use these dialogues**, replacing their custom dialogues.

Clear this field to revert to using each Ukagaka's default dialogue.

---

## AI Feature Settings

Go to **Settings** → **MP Ukagaka** → **AI Settings**

### Enable AI Page Awareness

Check "Enable Page Awareness" to turn on AI features.

---

## LLM Feature Settings (BETA)

> ⚠️ **IMPORTANT**: The LLM feature is currently in **BETA**. Functionality may be unstable, please use with caution.

Go to **Settings** → **MP Ukagaka** → **LLM Settings**

### What is the LLM Feature?

The LLM (Large Language Model) feature allows you to use local or remote Ollama services to generate dialogue, **completely free**, with no API Key required.

### Prerequisites

1. **Install Ollama**

   - Go to [Ollama Official Website](https://ollama.ai/) to download and install.
   - Start the Ollama service.
   - Download a model: Run `ollama pull qwen3:8b` (or your preferred model) in the terminal.

2. **Verify Ollama is Running**
   - Local: Visiting `http://localhost:11434` should show "Ollama is running".
   - Remote: Ensure your Cloudflare Tunnel or other tunnel service is running properly.

### Basic Settings

#### 1. Enable LLM

- Check "Enable LLM (Ollama)".
- The system will automatically switch the AI provider to Ollama.

#### 2. Configure Endpoint

**Local Connection:**

```
http://localhost:11434
```

**Remote Connection (Cloudflare Tunnel):**

```
https://your-domain.com
```

> 💡 The plugin automatically detects connection type (local/remote) and adjusts timeout settings.

#### 3. Set Model Name

Enter the name of the model you downloaded, for example:

- `qwen3:8b`
- `llama3.2`
- `mistral`

> 💡 Use the `ollama list` command in PowerShell to view downloaded models.

#### 4. Test Connection

Click the "Test Ollama Connection" button to confirm the connection is working.

### Advanced Settings

#### Use LLM to Replace Built-in Dialogue

After enabling this option:

- All Ukagaka dialogue will be generated by LLM in real-time.
- The default static dialogue list will no longer be used.

> 💡 **Tip**: This feature can be enabled simultaneously with "Page Awareness". When Page Awareness conditions are met, it will use AI to comment on the article; otherwise, it will use randomly generated dialogue.

**Use Cases:**

- Want completely dynamic dialogue content.
- Do not need default static dialogue.
- Wish for every dialogue to be unique.

#### Customize LLM Prompt System

> 💡 **Advanced Feature**: If you want to adjust the LLM generated dialogue style according to the character's personality, you can customize the prompt system.

**Default Prompt Style:**

Currently, the system default prompt is based on **Frieren style**, emphasizing:

- Quiet, natural, unassuming dialogue.
- Soft-spoken tone.
- Plain daily observations.
- Not forced, natural interaction.

**How to Customize Prompts:**

1. **Find Prompt Definition Location**
   - File path: `includes/llm-functions.php`
   - Function: `mpu_generate_llm_dialogue()`
   - Approximately lines 255-300.

2. **Prompt Category Structure**

   Prompts are divided into multiple categories. The system randomly selects a category, then randomly selects a prompt from that category:

   ```php
   $prompt_categories = [
       'greeting' => [        // Greetings
           "Softly say hello",
           "Simply greet the user",
           // ... more prompts
       ],
       'casual' => [          // Casual chat
           "Say something that comes to mind",
           "Say a plain daily observation",
           // ... more prompts
       ],
       'observation' => [     // Observation/Thinking
           "Say something just noticed",
           "Share a quiet observation",
           // ... more prompts
       ],
       'contextual' => [       // Contextual (Combined with time)
           "It is now {$time_context}, say something suitable for this time",
           // ... more prompts
       ],
       'wordpress_info' => [  // WordPress Info
           "Say a word about this site running on WordPress {$wp_version}",
           "The theme is '{$theme_name}' version {$theme_version}",
           // ... more prompts
       ],
       'statistics' => [      // Statistics (Gamified style)
           "Lightly mention the site has encountered demons {$post_count} times",
           "Comment on the damage dealt by the admin: {$comment_count}",
           // ... more prompts
       ],
   ];
   ```

3. **WordPress Info Integration**

   The system automatically adds WordPress site info to the dialogue generation background knowledge, including:

   **Basic System Info:**
   - WordPress version
   - Current theme name, version, author
   - PHP version
   - Site name and description
   - Active plugin count

   > 💡 **Tip**: Theme author info (`$theme_author`) is only available if the theme provides it; some themes may not include this.

   **Statistics (Gamified Terms):**
   - **Demon Encounters** (Post Count): `{$post_count}`
   - **Max Damage** (Comment Count): `{$comment_count}`
   - **Skills Learned** (Category Count): `{$category_count}`
   - **Items Used** (Tag Count): `{$tag_count}`
   - **Adventure Days** (Days Operating): `{$days_operating}`

   These details are automatically added to the `system_prompt` as background knowledge. You can use the following variables in prompts:

   ```php
   // Basic Info Variables
   $wp_version      // WordPress Version
   $theme_name      // Theme Name
   $theme_version   // Theme Version
   $theme_author    // Theme Author
   $php_version     // PHP Version
   $post_count      // Post Count
   $comment_count   // Comment Count
   $category_count  // Category Count
   $tag_count       // Tag Count
   $days_operating  // Days Operating
   ```

   > 💡 **Tip**: Statistics use a transient cache for 5 minutes to avoid frequent database queries affecting performance.

4. **Personalized Statistics Prompt Example**

   Here is an example of gamifying statistics (suitable for "Frieren: Beyond Journey's End" characters):

   ```php
   // Statistics (Gamified style - Demon Battle style)
   'statistics' => [
       "Lightly mention that this site has encountered demons {$post_count} times",
       "Say a word about the damage dealt by the admin: {$comment_count}",
       "Comment on the number of times the admin used items: {$tag_count}",
       "Learned {$category_count} skills (categories), lightly mention this",
   ];
   
   // If operating days > 0, add related prompts
   if ($days_operating > 0) {
       $prompt_categories['statistics'][] = "This site's adventure has lasted {$days_operating} days... a long journey, say a word about this";
       $prompt_categories['statistics'][] = "Admin has been at it for {$days_operating} days, comment on this";
   }
   
   // Combine multiple statistics prompts
   $prompt_categories['statistics'][] = "This site has encountered demons {$post_count} times, admin dealt {$comment_count} damage, say a word about this";
   $prompt_categories['statistics'][] = "Admin used items {$tag_count} times, learned {$category_count} skills, lightly mention this";
   ```

   **Prompt Design Points:**

   - ✅ **Be Specific**: Clearly state the statistic item in the prompt (e.g., "Demon encounters", "Damage dealt") so the user understands the context.
   - ✅ **Character Fit**: Transform statistics into terms fitting the character's worldview (e.g., "Demons", "Items", "Skills").
   - ✅ **Natural Expression**: Use instructions like "Lightly mention", "Say a word about" to let AI generate more natural dialogue.
   - ❌ **Avoid Just Numbers**: Don't just say "{$post_count} times", explain what happened that many times.

5. **Customization Examples**

   **Energetic Character Style:**

   ```php
   'greeting' => [
       "Greet proactively!",
       "Excitefully say hello to the user",
       "Loudly say: Hello!",
   ],
   'casual' => [
       "Share an interesting story",
       "Tell a joke",
       "Talk about something fun that happened today",
   ],
   ```

   **Quiet Character Style:**

   ```php
   'greeting' => [
       "Softly say hello",
       "Simply greet the user",
       "Say a discreet greeting",
   ],
   'casual' => [
       "Say something that comes to mind",
       "Say a plain daily observation",
   ],
   ```

   **Tsundere Character Style:**

   ```php
   'greeting' => [
       "Hmph, you're here.",
       "I don't want to admit it, but I'll say hello.",
       "Reluctantly say a word to you.",
   ],
   'casual' => [
       "I-It's not like I want to chat with you.",
       "Just thought of it randomly.",
   ],
   ```

6. **Time Variable Usage**

   In the `contextual` category, you can use the `{$time_context}` variable, which automatically replaces with:
   - `Morning` (5:00-11:59)
   - `Afternoon` (12:00-17:59)
   - `Evening` (18:00-21:59)
   - `Late Night` (22:00-4:59)

   > ⚠️ **Note**: Time logic uses Taiwan Time Zone (Asia/Taipei). It will display Taiwan time correctly even if the server is in another timezone.

7. **Anti-Repetition Mechanism**

   The system automatically tracks the last response generated by the LLM to prevent repeating the same content in auto-dialogue:

   - When LLM generates dialogue, the system records this response.
   - Next time auto-dialogue triggers, the last response is passed to LLM.
   - LLM will generate different content based on the prompt or remain silent.
   - Effectively avoids "nonsense loop" issues.

   > 💡 This mechanism is handled automatically in the backend, no extra setup needed.

8. **Idle Detection**

   The system automatically detects user activity status and pauses auto-dialogue when the user is idle:

   - **Idle Threshold**: 60 seconds (1 minute).
   - **Activity Detection**: Mouse movement, keyboard input, page scroll, clicks.
   - **Auto Resume**: Auto-dialogue resumes automatically when user becomes active.
   - **Resource Saving**: Avoids wasting GPU and network resources in background tabs or when user is away.

   > 💡 Idle threshold can be adjusted in `ukagaka-core.js` via the `mpuIdleThreshold` constant (default 60000ms).

9. **Notes After Modification**

   - Clear WordPress cache after modification (if applicable).
   - Recommend testing a few dialogues to ensure style meets expectations.
   - Adjust the number of prompts in different categories based on character personality.
   - More specific prompts lead to more consistent dialogue styles.

**Prompt Design Suggestions:**

- ✅ **Fit Character**: Design prompts according to the character's personality.
- ✅ **Diversity**: Recommend 4-6 different prompts per category.
- ✅ **Natural Expression**: Use natural language, avoid being too mechanical.
- ✅ **Avoid Contradictions**: Ensure prompt consistency within the same category.
- 💡 **Length Recommendations**:
  - **Cloud AI Services** (Gemini, OpenAI, Claude): Suggest under 200-300 words to control API costs.
  - **Local LLM** (Ollama): Can use longer prompts (1000+ words); detailed prompts usually provide better character consistency and personality definition.

#### Disable Thinking Mode (Beta Models like Qwen3)

After enabling this option:

- Disable thinking behavior for models like Qwen3.
- Improve response efficiency.
- Reduce response time.

**Recommendation:** Enable this option when using Qwen3 or similar models.

### Remote Connection Settings (Cloudflare Tunnel)

#### Using Cloudflare Tunnel

1. **Install Cloudflare Tunnel**

   ```bash
   # Windows
   cloudflared.exe service install <token>

   # Linux/Mac
   cloudflared service install <token>
   ```

2. **Verify Service Running**

   - Check Cloudflare Tunnel service status.
   - Confirm tunnel URL (e.g., `https://your-domain.com`).

3. **Configure in Plugin**
   - Endpoint: Enter Cloudflare Tunnel URL.
   - Test connection to confirm.

#### Other Tunnel Services

The plugin also supports other tunnel services:

- **ngrok**: `https://your-subdomain.ngrok.io`
- **Other HTTP/HTTPS tunnel services**

### Common Issues

#### LLM Connection Failed

1. **Local Connection Issues**

   - Confirm Ollama service is running.
   - Check if port is 11434.
   - Try visiting `http://localhost:11434` in browser.

2. **Remote Connection Issues**
   - Confirm Cloudflare Tunnel service is running.
   - Check if tunnel URL is correct.
   - Confirm network connection is normal.
   - Check firewall settings.

#### Slow Response Speed

1. **Local Connection**

   - Use a faster model (e.g., `qwen3:8b`).
   - Enable "Disable Thinking Mode".
   - Check local resource usage.

2. **Remote Connection**
   - Remote connections have extra latency (normal).
   - Consider using a faster network connection.
   - Check Cloudflare Tunnel latency.

#### Model Not Found

- Confirm model name is correct (use `ollama list` to view).
- Confirm model is downloaded (use `ollama pull <model>` to download).
- Check if model name case is correct.

### Notes

⚠️ **Beta Restrictions:**

- Functionality may be unstable.
- Connection issues may occur.
- Response time may be longer.
- Features may change in future versions.

💡 **Suggestions:**

- Try in a test environment first.
- Regularly backup settings.
- If issues occur, switch back to traditional AI features or static dialogue.

---

### Choose AI Provider

Three AI services are supported:

| Provider | Features | Get API Key |
| ------------- | ---------------- | ------------------------------------------------------------ |
| Google Gemini | Fast, high free tier | [Google AI Studio](https://makersuite.google.com/app/apikey) |
| OpenAI | GPT series models | [OpenAI Platform](https://platform.openai.com/api-keys) |
| Claude | Advanced reasoning | [Anthropic Console](https://console.anthropic.com/) |

### AI Setting Items

| Setting Item | Description | Suggested Value |
| --------------- | ------------------------------ | ----------- |
| API Key | Key for the corresponding AI service | — |
| Model | AI Model Version | Select as needed |
| Language | Language for AI response | Traditional Chinese |
| Personality | AI Personality Description (System Prompt) | See example below |
| AI Response Rate | Probability of triggering AI (1-100%) | 10-30% |
| Trigger Pages | Pages where AI triggers | `is_single` |
| AI Text Color | Text color of AI response | `#ff6b6b` |
| AI Display Time | How long AI response shows | 5-10 seconds |

### Personality Setting Example

**Tsundere Character:**

```
You are a tsundere desktop assistant "Ukagaka". You comment on article content with a short, slightly tsundere tone. Keep response under 40 words.
```

**Mage Character:**

```
You are the mage Frieren, speak in a calm, slightly cold tone, showing more interest in magic-related topics. Keep response under 50 words.
```

**Japanese Character:**

```
あなたは可愛いデスクトップマスコットです。記事について短く（40字以内）、明るくコメントしてください。
```

### Trigger Page Description

Use WordPress conditional tags, multiple conditions separated by commas:

| Tag | Description |
| --------------- | ------------ |
| `is_single` | Single Post Page |
| `is_page` | Static Page |
| `is_home` | Blog Home |
| `is_front_page` | Site Home |
| `is_category` | Category Page |
| `is_tag` | Tag Page |

**Example:** `is_single,is_page` triggers on both posts and pages.

### First Visitor Greeting

If enabled, displays a personalized welcome message to first-time visitors.

> 💡 Combine with Slimstat plugin for more visitor info (source, search keywords, etc.).

---

## Extensions

Go to **Settings** → **MP Ukagaka** → **Extensions**

### JS Area

You can add custom JavaScript code to add more interactive features for the Ukagaka.

**Example: Double click Ukagaka to jump to a specific page**

```javascript
document
  .getElementById("cur_ukagaka")
  .addEventListener("dblclick", function () {
    window.location.href = "/about/";
  });
```

### Special Codes

Use special codes in dialogue to display dynamic content:

| Code | Description |
| ----------------- | --------------------- |
| `:recentpost[5]:` | Show list of recent 5 posts |
| `:randompost[3]:` | Show 3 random posts |
| `:commenters[5]:` | Show recent 5 commenters |

**Dialogue Example:**

```
Recent posts: :recentpost[3]:
```

---

## FAQ

### Ukagaka Not Showing

1. Confirm "Default Show Ukagaka" is checked.
2. Check if current page is in the exclusion list.
3. Clear browser cache.
4. Check for JavaScript errors (Press F12 to view Console).

### AI Not Triggering

1. Confirm "Enable Page Awareness" is enabled.
2. Check if API Key is correct.
3. Confirm current page meets trigger conditions.
4. Temporarily set "AI Response Rate" to 100% to test.
5. Confirm article content is over 500 words.

### Dialogue Not Displaying Correctly

1. Check if dialogue file format is correct.
2. TXT Format: Separate each dialogue with an **empty line**.
3. JSON Format: Confirm valid JSON.

### AI Response Too Slow

1. Try switching to a faster model (e.g., `gemini-2.5-flash`).
2. **Cloud AI Services**: Shorten Personality (System Prompt) to reduce API processing time.
3. **Local LLM**: Prompt length has less impact on speed; prioritize adjusting model size or hardware.
4. Check internet connection.

### LLM Connection Failed

1. **Local Connection**

   - Confirm Ollama service is running.
   - Check if port is 11434.
   - Try visiting `http://localhost:11434` in browser.

2. **Remote Connection**
   - Confirm Cloudflare Tunnel service is running.
   - Check if tunnel URL is correct.
   - Confirm network connection is normal.

### LLM Response Slow

1. Use a faster model (e.g., `qwen3:8b`).
2. Enable "Disable Thinking Mode" option.
3. Remote connections have extra latency (normal).

### How to Control AI Costs

1. Lower "AI Response Rate" (Suggest 10-20%).
2. Limit "Trigger Pages" (Only trigger on `is_single`).
3. Use cheaper models.
4. **Or use LLM feature**: Completely free, no API Key (Beta).

---

## Technical Support

If you have issues, please:

1. Consult this User Guide.
2. Check the [FAQ](#faq) section.
3. Visit the [Maintainer's Blog](https://www.moelog.com/).

---

**Made with ❤ for WordPress**
