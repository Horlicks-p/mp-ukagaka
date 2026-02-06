# Slimstat API Integration Debugging Guide

This document explains how to verify if Slimstat API integration is correct and if AI can retrieve visitor source information.

## Enable Debug Mode

### Method 1: Browser Console (Recommended)

1. Open your website.
2. Press `F12` to open Developer Tools.
3. Switch to the "Console" tab.
4. Enter the following command to enable debug mode:

```javascript
window.mpuDebugMode = true
```

5. Refresh the page (or clear first visitor cookie and revisit).

### Method 2: WordPress Debug Mode

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

This will log detailed information about AI greetings in `wp-content/debug.log`.

## Check Items

### 1. Check if Slimstat is Detected

In the browser console, you should see debug info similar to:

```
=== Visitor Info Debug ===
Slimstat Enabled: true
Slimstat Debug Info: {
  class_exists: true,
  init_method_exists: true,
  get_recent_method_exists: true,
  referrers_found: 1,
  referer_extracted: "https://example.com",
  searchterms_found: 0,
  searchterms_extracted: "no_records",
  countries_found: 1,
  country_extracted: "Taiwan",
  cities_found: 1,
  city_extracted: "Taipei"
}
```

**If `Slimstat Enabled: false`**:

- Confirm Slimstat plugin is installed and activated.
- Confirm Slimstat plugin version supports API calls.

### 2. Check if Visitor Info is Correctly Retrieved

Debug info will show:

- **Referrer**: Visitor source URL.
- **Referrer Host**: Source domain.
- **Search Engine**: Search engine name (if any).
- **Search Query**: Search keywords (if any).
- **Is Direct**: Whether it's a direct visit.
- **Country (Slimstat)**: Country (from Slimstat).
- **City (Slimstat)**: City (from Slimstat).

### 3. Check if AI Received Visitor Info

If `WP_DEBUG` is enabled, `wp-content/debug.log` will show:

```
MP Ukagaka - AI Greeting Prompt:
  - Referrer: https://www.google.com/search?q=example
  - Referrer Host: www.google.com
  - Search Engine: google
  - Search Query: example
  - Is Direct: No
  - Country: Taiwan
  - City: Taipei
  - User Prompt: A visitor has come to the site for the first time. The visitor came from search engine "google", search query is "example". The visitor is from "Taiwan", "Taipei". Please greet them in a kind and friendly tone.
```

## FAQ

### Q: Slimstat Enabled shows false

**A:** Possible reasons:

1. Slimstat plugin not installed or not activated.
2. Slimstat version too old, does not support API.
3. Need to update Slimstat to the latest version.

### Q: All Slimstat info is "no_records"

**A:** Possible reasons:

1. It's the visitor's first visit, Slimstat hasn't recorded it yet.
2. Slimstat database has no history for this IP.
3. Slimstat geolocation feature is not enabled.
4. **Local Development Environment**: If in local environment (e.g., `localhost`, `.local` domain), Slimstat may not be able to get geolocation info because local IPs (like 127.0.0.1) cannot resolve location.

### Q: Country and City show "None", but Referrer is caught

**A:** This is normal, possible reasons:

1. **Local Environment Limit**: Local IP addresses cannot resolve geolocation.
2. **Slimstat Settings**: Check if geolocation tracking is enabled in Slimstat settings.
3. **Database Records**: Slimstat might not have recorded geolocation info for this visitor yet (Wait for Slimstat to track and record).

**Solutions**:

- Test in production environment: Real visitor IPs should resolve geolocation.
- Check Slimstat settings: Ensure Geolocation is enabled.
- Wait for records: Let Slimstat track a few visits before testing.

### Q: AI greeting does not mention visitor source

**A:** Check:

1. Confirm `Referrer` or `Search Engine` has value in debug info.
2. Check if AI `ai_greet_prompt` setting is correct.
3. View `debug.log` to confirm `User Prompt` passed to AI contains source info.

## Test Steps

1. **Clear First Visitor Cookie**:
   - Enter in browser console: `document.cookie.split(";").forEach(c => { if(c.includes("mpu_first_visit")) document.cookie = c.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/"; });`

2. **Enable Debug Mode**:
   - Enter: `window.mpuDebugMode = true`

3. **Simulate Different Sources**:
   - Direct: Type URL directly.
   - Search Engine: Click from Google search results.
   - External Site: Link from another site.

4. **Check Debug Info**:
   - Check console output.
   - Check if AI greeting content includes source info.

## Related Files

- `includes/ajax-handlers.php`: `mpu_ajax_get_visitor_info()` and `mpu_ajax_chat_greet()` functions.
- `ukagaka.js`: `mpu_greet_first_visitor()` function.
