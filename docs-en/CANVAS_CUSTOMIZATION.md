# Canvas Animation Features and CSS Customization Guide

> 🎨 Explains how the MP Ukagaka Canvas animation feature is implemented, and how to use CSS to adjust the character's position.

---

## 📑 Table of Contents

1. [Introduction to Canvas Animation](#introduction-to-canvas-animation)
2. [Animation Configuration](#animation-configuration)
3. [Frieren's Exclusive Decoration System](#frierens-exclusive-decoration-system)
4. [Technical Implementation Details](#technical-implementation-details)
5. [CSS Position Adjustments](#css-position-adjustments)
6. [Frequently Asked Questions](#frequently-asked-questions)

---

## Introduction to Canvas Animation

Starting from version 2.1.6, MP Ukagaka supports Canvas animations, replacing the original static `<img>` tags. This feature can:

- ✅ **Support single static images**: Backward compatible with the original single image settings.
- ✅ **Support multi-image animation**: Automatically detects folders and plays frame animations.
- ✅ **Play only when speaking**: Saves resources and improves performance.
- ✅ **Automatically load image sequences**: No need to manually specify the image order.

### Animation Characteristics

- **Frame Interval**: 180 milliseconds/frame (fixed)
- **Playback Timing**: Plays only when the character is speaking
- **Supported Formats**: `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`
- **Image Sorting**: Uses natural sort to ensure the correct order

---

## Animation Configuration

### Single Image Mode

When setting up the character in the admin panel, enter the **image file path** in the `shell` field:

```
images/shell/character.png
```

Or relative to the WordPress uploads directory:

```
2024/12/character.png
```

### Multi-Image Animation Mode

When setting up the character in the admin panel, enter the **folder path** in the `shell` field:

```
images/shell/Frieren/
```

The system will automatically:
1. Check if the path is a folder.
2. Scan all supported image files in the folder.
3. Sort the files using natural sort (e.g., `frame1.png`, `frame2.png`, ..., `frame12.png`).
4. Load all images and prepare the animation for playback.

**Notes:**
- The folder path must end with a `/`.
- Supported image formats: `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`.
- It is recommended to use numeric sequence names for images to ensure correct sorting.

---

## Frieren's Exclusive Decoration System

> 🎨 Frieren's (`default_1`) decorations are now driven by `ghost/Frieren/decorations.json`, replacing the hard-coded trio from earlier versions.

### Decoration Overview

The Frieren character will automatically load decorations based on `ghost/Frieren/decorations.json`:

| Decoration | File Name | Position | Z-Index | Purpose |
|------|---------|------|-----|------|
| Suitcase | `suitcase.png` | Front Right | `z-index: 10` | Frieren's travel suitcase |
| Evil Horns | `evil_horns.png` | Back Left | `z-index: -1` | Unknown purpose |
| Dark Dragon Horn | `dark_dragon_horn.png` | Front Left | Per JSON | Frieren's collected materials |
| Books | `books.png` | Back Right | Per JSON | Frieren's stack of magic books |
| Staff | `staff.png` | Back Right | Per JSON | Frieren's common staff |
| Potion | `potion.png` | Right Side | Per JSON | Special interaction item |

> 🖌️ **Interactive Features**: Click on any decoration, and Frieren will introduce the item!

### Decoration Positions and Sizes

The following values are just examples from the current `ghost/Frieren/decorations.json`. The actual configuration should refer to the JSON file.

#### 1. Suitcase

```javascript
{
    type: 'suitcase',
    src: decorationsBaseUrl + 'suitcase.png',
    top: '82%',        // Vertical position: 82% from the top
    right: '-62px',    // Horizontal position: 62px to the outside right
    width: '90px',     // Width
    height: 'auto',    // Height automatic
    transform: 'translateY(-50%)', // Vertical center
    zIndex: 10         // In front of the character image
}
```

#### 2. Evil Horns

```javascript
{
    type: 'evil_horns',
    src: decorationsBaseUrl + 'evil_horns.png',
    top: '42%',        // Vertical position: 42% from the top
    left: '-65px',     // Horizontal position: 65px to the outside left
    width: '135px',    // Width
    height: 'auto',    // Height automatic
    transform: 'translateY(-50%)', // Vertical center
    zIndex: -1         // Behind the character image
}
```

#### 3. Staff

```javascript
{
    type: 'staff',
    src: decorationsBaseUrl + 'staff.png',
    top: '25%',        // Vertical position: 25% from the top
    right: '-40px',    // Horizontal position: 40px to the outside right
    width: '110px',    // Width
    height: 'auto',    // Height automatic
    transform: 'translateY(-50%)', // Vertical center
    zIndex: 8
}
```

#### 4. Books

```javascript
{
    type: 'books',
    src: decorationsBaseUrl + 'books.png',
    top: '38%',
    right: '-58px',
    width: '115px',
    height: 'auto',
    transform: 'translateY(-50%)',
    zIndex: 6
}
```

### Decoration File Paths

Decoration images are stored by default at:

```
ghost/Frieren/decorations/
├── suitcase.png
├── evil_horns.png
├── dark_dragon_horn.png
├── staff.png
├── books.png
└── potion.png
```

The system will automatically derive the decoration paths from the following sources:

1. **Initialization Data** (Priority): `decorations_base_url` returned by `/init`.
2. **Frontend Global Variable**: `window.mpuDecorationsBaseUrl`.
3. **Frieren Manager Fallback**: Wait for the `mpuInitComplete` event and fetch settings if necessary.

### Enabling/Disabling Decorations

**Method 1: Using the Admin Panel (Recommended)**

1. Go to **Settings** → **MP Ukagaka** → **Ukagaka Management**.
2. Find the Frieren character (`default_1`).
3. Check or uncheck `show_decorations` for this character.
4. Save the settings.

**Method 2: Using CSS to Hide Specific Decorations (Advanced)**

```css
/* Hide all decorations */
.frieren-decoration {
    display: none !important;
}

/* Or hide a specific decoration */
.frieren-decoration.suitcase {
    display: none !important;
}
```

> 💡 **Tip**: The CSS method is suitable when you need to hide some decorations while keeping others.

### Adjusting Decoration Positions

#### Adjusting the Suitcase Position

```css
.frieren-decoration.suitcase {
    top: 85% !important;    /* Move downwards */
    right: -55px !important; /* Move left (reduce negative value) */
    width: 100px !important; /* Enlarge */
}
```

#### Adjusting the Evil Horns Position

```css
.frieren-decoration.evil_horns {
    top: 40% !important;    /* Move upwards */
    left: -70px !important; /* Move left (increase negative value) */
    width: 120px !important; /* Shrink */
}
```

#### Adjusting the Books Position

```css
.frieren-decoration.books {
    top: 40% !important;
    right: -50px !important;
    opacity: 0.8;           /* Adjust opacity */
}
```

### Technical Implementation Details

#### Decoration Loading Flow

1. **Check Character**: `mpuCanvasManager.isFrieren(num, name)`
   - Prioritizes checking if `num === 'default_1'`.
   - Also checks if the name contains `フリーレン` or `Frieren`.

2. **Initialize Frieren Mode**: `initFrierenMode()`
   - Sets the container to relative positioning.
   - Calls `loadFrierenDecorations()`.

3. **Load Decorations**: `loadFrierenDecorations()`
   - Prioritizes using `window.mpuDecorationConfig` and `window.mpuDecorationsBaseUrl` returned and written by `/init`.
   - Checks if `window.mpuShowDecorations` is enabled.
   - Creates elements one by one based on `items` in `decorations.json`.

4. **Add Decoration**: `addFrierenDecoration(config)`
   - Creates an `<img>` element.
   - Sets the `frieren-decoration` CSS class.
   - Applies absolute positioning styles.
   - Appends to the `#ukagaka_img` container.

#### Decoration HTML Structure

```html
<div id="ukagaka_img" style="position: relative;">
    <!-- Character Canvas or img -->
    <canvas id="cur_ukagaka">...</canvas>
    
    <!-- Decoration elements (added automatically based on decorations.json) -->
    <img class="frieren-decoration suitcase" 
         src=".../ghost/Frieren/decorations/suitcase.png"
         style="position: absolute; top: 82%; right: -62px; ...">
    
    <img class="frieren-decoration evil_horns" 
         src=".../ghost/Frieren/decorations/evil_horns.png"
         style="position: absolute; top: 42%; left: -65px; ...">
    
    <img class="frieren-decoration books" 
         src=".../ghost/Frieren/decorations/books.png"
         style="position: absolute; top: 38%; right: -58px; ...">
</div>
```

#### JavaScript API

```javascript
// Check if it is Frieren
mpuCanvasManager.isFrieren(num, name);

// Manually add a decoration (advanced usage)
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_accessory',
    src: 'path/to/accessory.png',
    top: '50%',
    right: '-40px',
    width: '80px',
    zIndex: 5
});

// Remove a specific decoration
mpuCanvasManager.removeFrierenDecoration('suitcase');

// Clear all decorations
mpuCanvasManager.clearFrierenDecorations();
```

### Custom Decoration Examples

#### Adding a New Decoration

```javascript
// It is recommended to add to ghost/Frieren/decorations.json instead of hardcoding JS
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_hat',
    src: decorationsBaseUrl + 'custom_hat.png',
    top: '10%',
    left: '50%',
    width: '60px',
    transform: 'translateX(-50%)', // Horizontally center
    zIndex: 20
});
```

#### Dynamically Controlling Decorations

```javascript
// Add a decoration on a specific event
document.addEventListener('some_event', function() {
    if (mpuCanvasManager.isFrierenMode) {
        mpuCanvasManager.addFrierenDecoration({
            type: 'special_effect',
            src: '/path/to/effect.png',
            top: '30%',
            left: '30%',
            width: '50px',
            zIndex: 15
        });
        
        // Remove after 5 seconds
        setTimeout(function() {
            mpuCanvasManager.removeFrierenDecoration('special_effect');
        }, 5000);
    }
});
```

### Decoration Compatibility

- ✅ **Supports Character Switching**: Decorations are automatically cleared when switching to other characters.
- ✅ **Supports Responsive Design**: Decorations use percentage positioning, automatically adapting to different screen sizes.
- ✅ **Supports Opacity**: Decoration opacity can be adjusted via CSS `opacity`.
- ✅ **Supports Z-Index**: Decoration layering (foreground/background) can be freely adjusted.

### Common Customization Needs

#### 1. Hide the suitcase, keep others

```css
.frieren-decoration.suitcase {
    display: none !important;
}
```

#### 2. Send all decorations to the background

```css
.frieren-decoration {
    z-index: -1 !important;
}
```

#### 3. Adjust opacity of all decorations

```css
.frieren-decoration {
    opacity: 0.7 !important;
}
```

#### 4. Hide decorations on small screens

```css
@media (max-width: 768px) {
    .frieren-decoration {
        display: none !important;
    }
}
```

---

## Technical Implementation Details

### Frontend Architecture

#### 1. HTML Structure

```html
<div id="ukagaka_img">
    <canvas id="cur_ukagaka" 
            data-title="Character Name"
            data-alt="Character Name"
            data-shell="Image path or folder path">
    </canvas>
</div>
```

#### 2. JavaScript Manager

The animation functionality is managed by the `mpuCanvasManager` object in `ukagaka-anime.js`:

```javascript
// Initialize Canvas
window.mpuCanvasManager.init(shellInfo, name);

// shellInfo structure:
{
    type: 'single' | 'folder',  // Single image or folder
    url: 'Image or folder URL',
    images: ['frame1.png', 'frame2.png', ...]  // Only present in folder mode
}
```

#### 3. Animation Playback Control

- **Start Playback**: `mpuCanvasManager.playAnimation()`
- **Stop Playback**: `mpuCanvasManager.stopAnimation()`
- **Check Mode**: `mpuCanvasManager.isAnimationMode()`

Animations will automatically play in the following situations:
- The character starts speaking (Triggered by the `mpu_typewriter` function).
- System messages are excluded (e.g., "Thinking...", "(Umm... what should I say...)").

#### 4. Backend Functions

The PHP function `mpu_get_shell_info($num)` is currently located in `includes/core/ukagaka-functions.php` and is responsible for:
- Detecting whether the `shell` path is a file or a folder.
- Scanning for image files in the folder.
- Returning the `shell_info` structure to the frontend.

---

## CSS Position Adjustments

### Main CSS Selectors

#### 1. Character Shell Position (Entire Block)

```css
#ukagaka_shell {
    position: fixed;    /* Fixed on the page */
    right: 0;          /* Align to the right */
    bottom: 20px;      /* 20px from the bottom */
    margin: 0 20px 0 0; /* 20px right margin */
    z-index: 10000;    /* Layering */
}
```

**Adjustment methods:**
- `bottom`: Adjust the vertical position (Increase to move up, decrease to move down).
- `right`: Adjust the horizontal position (Increase to move left, decrease to move right).
- `margin`: Adjust the outer margins.

#### 2. Character Image Container Position

```css
#ukagaka_img {
    margin-bottom: -10px; /* Vertical offset */
    /* margin-left: 30px; You can add this to move the image to the right */
}
```

**Adjustment methods:**
- `margin-bottom`: Adjust the vertical position (Negative values move up, positive values move down).
- `margin-left`: Move the image to the right (Add this property and set the value).
- `margin-right`: Move the image to the left.

#### 3. Canvas Element Styles

```css
#ukagaka_img canvas {
    opacity: 0.85; /* 85% opacity */
}
```

**Adjustment methods:**
- `opacity`: Opacity (0.0 fully transparent ~ 1.0 fully opaque).

#### 4. Dialog Box Position

```css
#ukagaka_msgbox {
    position: absolute;
    top: 50%;          /* Vertically center */
    left: -200px;      /* Offset 200px to the left (displays on the left of the character) */
    transform: translateY(-50%); /* Vertical centering adjustment */
}
```

**Adjustment methods:**
- `left`: Adjust the distance between the dialog box and the character (Negative values move left, positive values move right).
- `top`: Adjust the vertical position.

#### 5. Main Container Padding

```css
#ukagaka {
    padding-right: 40px; /* Right padding, leaves space for the dialog box */
}
```

**Adjustment methods:**
- `padding-right`: Adjust the right padding (Affects the spacing between the dialog box and the character).

### Practical Adjustment Examples

#### Example 1: Move the character 20px to the right

```css
#ukagaka_img {
    margin-left: 20px; /* Add this line */
}
```

#### Example 2: Adjust Opacity

```css
#ukagaka_img canvas {
    opacity: 0.9; /* Change to 90% */
}
```

#### Example 3: Adjust vertical position

```css
#ukagaka_img {
    margin-bottom: -20px; /* Move further upwards */
}
```

#### Example 4: Adjust distance between dialog box and character

```css
#ukagaka_msgbox {
    left: -180px; /* Decrease distance (closer to character) */
}

/* Simultaneously adjust the main container padding */
#ukagaka {
    padding-right: 30px; /* Decrease padding */
}
```

---

## Frequently Asked Questions

### Q: Animation is not playing?

**A:** Please check:
1. Are all images fully loaded? (Check the browser console for errors).
2. Is the character speaking? (Animations only play when speaking).
3. Is the message a system message? (System messages do not trigger animations).

### Q: Image order is incorrect?

**A:** Please ensure the image file names use numeric sequences, for example:
- ✅ `frame1.png`, `frame2.png`, ..., `frame12.png`
- ✅ `001.png`, `002.png`, ..., `012.png`
- ❌ `frame_a.png`, `frame_b.png` (Alphabetical sorting might not be correct)

The system will use natural sort, so numbers will be ordered correctly.

### Q: How do I go back to using the `<img>` tag?

**A:** The Canvas functionality has completely replaced the `<img>` tag, but it still supports single images. If you need to use a single image, just enter the image file path (not a folder) in the `shell` field.

### Q: Can I adjust the animation playback speed?

**A:** Currently, the animation frame interval is fixed at 180 milliseconds/frame. If you need to adjust it, you can modify the `frameInterval` property in `ukagaka-anime.js`:

```javascript
frameInterval: 180, // Change to another value (in milliseconds)
```

### Q: CSS modifications did not take effect?

**A:** Please check:
1. Are you overriding with `!important`? (Some themes might use `!important`).
2. Has the browser cache been cleared?
3. Is the CSS selector correct?
4. Are there other CSS rules overriding your settings?

---

## Related Files

- `js/ukagaka-anime.js` - Canvas animation manager
- `mpu_style.css` - Main stylesheet file
- `ghost/Frieren/frieren.js` - Frieren's exclusive decoration and interaction logic
- `ghost/Frieren/decorations.json` - Decoration configurations
- `includes/core/ukagaka-functions.php` - `mpu_get_shell_info()` function
- `includes/core/frontend-functions.php` - HTML generation and initialization data injection
- `js/ukagaka-core.js` - Animation trigger logic

---

## Changelog

- **2.1.6** - Initially implemented Canvas animation functionality
- **2.12.x+** - Frieren's decorations are now driven by `ghost/Frieren/decorations.json`

---

**Made with ❤ for WordPress**
