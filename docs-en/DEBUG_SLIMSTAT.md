# Slimstat Integration Debugging Guide

This document explains how to confirm whether Slimstat data is correctly integrated into MP Ukagaka, and whether the page-aware AI / first-time visitor greeting can successfully fetch visitor source information.

## Enabling Debug Mode

### Method 1: Browser Console (Recommended)

1. Open your website.
2. Press `F12` to open the Developer Tools.
3. Switch to the "Console" tab.
4. Enter the following command to enable debug mode:

```javascript
window.mpuDebugMode = true
```

5. Refresh the page (or clear the first-visit cookie and revisit).

### Method 2: WordPress Debug Mode

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

This will write PHP-side errors to `wp-content/debug.log`; frontend details will still primarily rely on the browser console.

## Items to Check

### 1. Check if Slimstat is detected

In the browser console, the current frontend will typically output information similar to the following:

```
[MP Ukagaka] Visitor Info: {
  referrer: "https://example.com",
  referrer_host: "example.com",
  search_engine: "google",
  country: "TW",
  city: "Taipei"
}
```

The actual data fields come from `/wp-json/mp-ukagaka/v1/visitor-info`, and the backend implementation is located in `includes/rest/class-mpu-rest-dialog.php`.

**If `slimstat_enabled` is false**:
- Confirm that the Slimstat plugin is installed and activated.
- Confirm that the database table exists and the plugin can read Slimstat records.

### 2. Check if visitor information is correctly fetched

Currently, the frontend uses or displays the following information:
- **Referrer**: Visitor source URL
- **Referrer Host**: Source domain
- **Search Engine**: Search engine name (if any)
- **Is Direct**: Whether it's a direct visit
- **Country (Slimstat)**: Country (from Slimstat)
- **City (Slimstat)**: City (from Slimstat)

### 3. Check if the AI receives visitor information

Currently, the first-time visitor greeting flow is:

1. The frontend first calls `GET /visitor-info`.
2. It then sends the structured data to `POST /chat/greet`.

Related code locations:

- `js/ukagaka-greeting.js`
- `includes/rest/class-mpu-rest-dialog.php`
- `includes/rest/class-mpu-rest-chat.php`

If `window.mpuDebugMode = true` is enabled, you can verify in the Console whether the visitor information and subsequent flows were successfully logged.

If `WP_DEBUG` / `WP_DEBUG_LOG` is enabled, you can observe `wp-content/debug.log` for PHP-side errors; however, the fixed-format full greet prompt output from earlier documentation versions is no longer guaranteed to be present.

You can manually inspect the browser's Network panel:

```
GET  /wp-json/mp-ukagaka/v1/visitor-info
POST /wp-json/mp-ukagaka/v1/chat/greet
```

## Frequently Asked Questions

### Q: `slimstat_enabled` shows false

**A:** Possible reasons:
1. The Slimstat plugin is not installed or activated.
2. The Slimstat data table does not exist, or the site has not yet generated readable records.
3. Environmental restrictions prevent obtaining the corresponding visitor records.

### Q: All Slimstat information is "no_records"

**A:** Possible reasons:
1. This is the visitor's first visit, and Slimstat hasn't recorded it yet.
2. There is no historical record of the IP in Slimstat's database.
3. Slimstat's geolocation feature is not enabled.
4. **Local Development Environment**: If it is a local environment (e.g., `localhost`, `.local` domains), Slimstat might not be able to get geolocation info because local IPs (like 127.0.0.1) cannot be resolved geographically.

### Q: Country and City show "None", but Referrer is captured

**A:** This is normal, possible reasons:
1. **Local Environment Limitations**: IP addresses in local development environments (e.g., `wordsworth.wp.local`) cannot be resolved geographically.
2. **Slimstat Settings**: Check if the geolocation tracking feature is enabled in Slimstat settings.
3. **Database Records**: Slimstat might not have recorded the visitor's geolocation information yet (you need to wait for Slimstat to track and record it).

**Solutions**:
- Test in a production environment: After deploying to a live server, real visitor IPs should be able to fetch geolocation information.
- Check Slimstat settings: Confirm that the geolocation tracking feature is enabled.
- Wait for records: Let Slimstat track a few visits before testing again.

### Q: The AI greeting does not mention the visitor source

**A:** Check:
1. Confirm that `referrer` or `search_engine` have values in the `/visitor-info` response.
2. Check if the AI's `ai_greet_prompt` setting is configured correctly.
3. Use the browser's Network panel to inspect if the `/chat/greet` request payload contains `referrer`, `referrer_host`, `search_engine`, `country`, and `city`.

## Testing Steps

1. **Clear First Visit Cookie**:
   - Enter in the browser console: `document.cookie.split(";").forEach(c => { if(c.includes("mpu_first_visit")) document.cookie = c.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/"; });`

2. **Enable Debug Mode**:
   - Enter: `window.mpuDebugMode = true`

3. **Simulate Different Source Visits**:
   - Direct visit: Type the URL directly.
   - Search Engine: Click through from Google search results.
   - External website: Click through from a link on another website.

4. **View Debug Information**:
   - Check the `Visitor Info` log in the Console.
   - Inspect `/visitor-info` and `/chat/greet` in the Network panel.
   - Check if the AI greeting content contains source information.

## Related Files

- `includes/rest/class-mpu-rest-dialog.php`: `/visitor-info`
- `includes/rest/class-mpu-rest-chat.php`: `/chat/greet`
- `js/ukagaka-greeting.js`: `mpu_greet_first_visitor()` function
- `js/ukagaka-context.js`: Page-aware AI also reads `visitor-info`
