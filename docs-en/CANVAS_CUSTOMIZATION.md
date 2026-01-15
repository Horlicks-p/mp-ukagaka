# Canvas Animation & CSS Customization Guide

> 🎨 Explains how to implement MP Ukagaka's Canvas animation features and how to adjust Ukagaka positioning via CSS

---

## 📑 Table of Contents

1. [Canvas Animation Features Introduction](#canvas-animation-features-introduction)
2. [Animation Setup](#animation-setup)
3. [Frieren's Exclusive Decorations System](#frierens-exclusive-decorations-system)
4. [Technical Implementation Details](#technical-implementation-details)
5. [CSS Position Adjustment](#css-position-adjustment)
6. [FAQ](#faq)

---

## Canvas Animation Features Introduction

Starting from version 2.1.6, MP Ukagaka supports Canvas animation features, replacing the original static `<img>` tag. This feature allows:

- ✅ **Support for Single Static Image**: Backward compatible with original single image settings.
- ✅ **Support for Multi-Image Animation**: Automatically detects folders and plays frame animation.
- ✅ **Play Only When Speaking**: Saves resources and improves performance.
- ✅ **Auto Load Image Sequence**: No need to manually specify image order.

### Animation Characteristics

- **Frame Interval**: 100 ms/frame (Fixed).
- **Playback Timing**: Plays only when the character is speaking.
- **Supported Formats**: `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`.
- **Image Sorting**: Uses natural sort to ensure correct order.

---

## Animation Setup

### Single Image Mode

In the backend Ukagaka settings, enter the **image file path** in the `shell` field:

```
images/shell/character.png
```

Or relative to the WordPress upload directory:

```
2024/12/character.png
```

### Multi-Image Animation Mode

In the backend Ukagaka settings, enter the **folder path** in the `shell` field:

```
images/shell/Frieren/
```

The system will automatically:

1. Detect if the path is a directory.
2. Scan all supported image files in the directory.
3. Sort by filename naturally (e.g., `frame1.png`, `frame2.png`, ..., `frame12.png`).
4. Load all images and prepare for animation playback.

**Notes:**

- Folder path must end with `/`.
- Supported image formats: `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`.
- Image filenames are recommended to use numeric sequences for correct sorting.

---

## Frieren's Exclusive Decorations System

> 🎨 **v2.2.1+ New Feature**: Default character Frieren (`default_1`) has an exclusive decorations system

### Decorations Overview

Frieren character automatically loads three exclusive decorations (アクセサリー) without manual configuration:

| Decoration | Filename | Position | Z-Index | Purpose |
|------|---------|------|-----|------|
| Suitcase | `suitcase.png` | Right front | `z-index: 10` | Frieren's travel suitcase |
| Giant Skull | `evil_horns.png` | Left back | `z-index: -1` | Don't know what it's useful for |
| Magic Book | `magic_book.png` | Right back top | `z-index: -1` | Frieren's spellbook |
| Magic Staff | `magic_staff.png` | Right back bottom | `z-index: -1` | Frieren's magic staff |

> 🖌️ **Interactive Feature**: Click on any decoration and Frieren will introduce the item!

### Decoration Position & Size

#### 1. Suitcase

```javascript
{
    type: 'suitcase',
    src: decorationsBaseUrl + 'suitcase.png',
    top: '82%',        // Vertical position: 82% from top
    right: '-62px',    // Horizontal position: 62px to the right outside
    width: '90px',     // Width
    height: 'auto',    // Height auto
    transform: 'translateY(-50%)', // Vertical center
    zIndex: 10         // In front of character image
}
```

#### 2. Giant Skull

```javascript
{
    type: 'evil_horns',
    src: decorationsBaseUrl + 'evil_horns.png',
    top: '42%',        // Vertical position: 42% from top
    left: '-65px',     // Horizontal position: 65px to the left outside
    width: '135px',    // Width
    height: 'auto',    // Height auto
    transform: 'translateY(-50%)', // Vertical center
    zIndex: -1         // Behind character image
}
```

#### 3. Books & Staff

```javascript
{
    type: 'books_staff',
    src: decorationsBaseUrl + 'books_staff.png',
    top: '25%',        // Vertical position: 25% from top
    right: '-60px',    // Horizontal position: 60px to the right outside
    width: '135px',    // Width
    height: 'auto',    // Height auto
    transform: 'translateY(-50%)', // Vertical center
    zIndex: -1         // Behind character image
}
```

### Decoration File Path

Decoration images are stored by default in:

```
images/decorations/
├── suitcase.png       # Suitcase
├── evil_horns.png     # Giant skull
├── magic_book.png     # Magic Book
└── magic_staff.png    # Magic Staff
```

System automatically derives decoration paths from the following sources:

1. **PHP Global Variable** (Priority): `window.mpuDecorationsBaseUrl`
2. **Shell Path Derivation**: Derive from `shell/Frieren/` to `decorations/`
3. **Script Path Derivation** (Fallback): Derive from `js/ukagaka-anime.js`

### Enable/Disable Decorations

**Method 1: Using Backend Settings (Recommended)**

1. Go to **Settings** → **MP Ukagaka** → **Ukagaka Management**
2. Find the Frieren character (`default_1`)
3. Check or uncheck the "**Show Exclusive Decorations**" option
4. Save settings

**Method 2: Hide Specific Decorations with CSS (Advanced)**

```css
/* Hide all decorations */
.frieren-decoration {
    display: none !important;
}

/* Or hide specific decoration */
.frieren-decoration.suitcase {
    display: none !important;
}
```

> 💡 **Tip**: CSS method is suitable when you need to hide some decorations but keep others.

### Adjusting Decoration Position

#### Adjust Suitcase Position

```css
.frieren-decoration.suitcase {
    top: 85% !important;    /* Move down */
    right: -55px !important; /* Move left (reduce negative value) */
    width: 100px !important; /* Enlarge */
}
```

#### Adjust Giant Skull Position

```css
.frieren-decoration.evil_horns {
    top: 40% !important;    /* Move up */
    left: -70px !important; /* Move left (increase negative value) */
    width: 120px !important; /* Shrink */
}
```

#### Adjust Books & Staff Position

```css
.frieren-decoration.books_staff {
    top: 30% !important;    /* Move down */
    right: -50px !important; /* Move left (reduce negative value) */
    opacity: 0.8;           /* Adjust opacity */
}
```

### Technical Implementation Details

#### Decoration Loading Flow

1. **Check Character**: `mpuCanvasManager.isFrieren(num, name)`
   - Check `num === 'default_1'`
   - Check `name` contains 'フリーレン' or 'Frieren'

2. **Initialize Frieren Mode**: `initFrierenMode()`
   - Set container to relative positioning
   - Call `loadFrierenDecorations()`

3. **Load Decorations**: `loadFrierenDecorations()`
   - Check if `window.mpuShowDecorations` is enabled
   - Derive decoration image base URL
   - Add decoration elements one by one

4. **Add Decoration**: `addFrierenDecoration(config)`
   - Create `<img>` element
   - Set `frieren-decoration` CSS class
   - Apply absolute positioning styles
   - Append to `#ukagaka_img` container

#### Decoration HTML Structure

```html
<div id="ukagaka_img" style="position: relative;">
    <!-- Ukagaka Canvas or img -->
    <canvas id="cur_ukagaka">...</canvas>
    
    <!-- Decoration elements (automatically added) -->
    <img class="frieren-decoration suitcase" 
         src=".../decorations/suitcase.png"
         style="position: absolute; top: 82%; right: -62px; ...">
    
    <img class="frieren-decoration evil_horns" 
         src=".../decorations/evil_horns.png"
         style="position: absolute; top: 42%; left: -65px; ...">
    
    <img class="frieren-decoration books_staff" 
         src=".../decorations/books_staff.png"
         style="position: absolute; top: 25%; right: -60px; ...">
</div>
```

#### JavaScript API

```javascript
// Check if it's Frieren
mpuCanvasManager.isFrieren(num, name);

// Manually add decoration (advanced usage)
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_accessory',
    src: 'path/to/accessory.png',
    top: '50%',
    right: '-40px',
    width: '80px',
    zIndex: 5
});

// Remove specific decoration
mpuCanvasManager.removeFrierenDecoration('suitcase');

// Clear all decorations
mpuCanvasManager.clearFrierenDecorations();
```

### Custom Decoration Examples

#### Adding New Decoration

```javascript
// Add in loadFrierenDecorations() function in ukagaka-anime.js
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_hat',
    src: decorationsBaseUrl + 'custom_hat.png',
    top: '10%',
    left: '50%',
    width: '60px',
    transform: 'translateX(-50%)', // Horizontal center
    zIndex: 20
});
```

#### Dynamic Decoration Control

```javascript
// Add decoration on specific event
document.addEventListener('someEvent', function() {
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

- ✅ **Character Switching Support**: Decorations automatically clear when switching to other characters
- ✅ **Responsive Support**: Decorations use percentage positioning to adapt to different screen sizes
- ✅ **Opacity Support**: Decoration opacity adjustable via CSS `opacity`
- ✅ **Z-index Support**: Freely adjust decoration layers (foreground/background)

### Common Customization Needs

#### 1. Hide Suitcase, Keep Other Decorations

```css
.frieren-decoration.suitcase {
    display: none !important;
}
```

#### 2. Put All Decorations in Background

```css
.frieren-decoration {
    z-index: -1 !important;
}
```

#### 3. Adjust Opacity of All Decorations

```css
.frieren-decoration {
    opacity: 0.7 !important;
}
```

#### 4. Hide Decorations on Small Screens

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
            data-title="Ukagaka Name"
            data-alt="Ukagaka Name"
            data-shell="Image path or Folder path">
    </canvas>
</div>
```

#### 2. JavaScript Manager

The animation feature is managed by the `mpuCanvasManager` object in `ukagaka-anime.js`:

```javascript
// Initialize Canvas
window.mpuCanvasManager.init(shellInfo, name);

// shellInfo structure:
{
    type: 'single' | 'folder',  // Single image or Folder
    url: 'URL of image or folder',
    images: ['frame1.png', 'frame2.png', ...]  // Only has value in folder mode
}
```

#### 3. Animation Playback Control

- **Start Playback**: `mpuCanvasManager.playAnimation()`
- **Stop Playback**: `mpuCanvasManager.stopAnimation()`
- **Check Mode**: `mpuCanvasManager.isAnimationMode()`

Animation will automatically play when:

- Character starts speaking (`mpu_typewriter` function triggered).
- Excluding system messages (e.g., "Thinking...", "(Umm... what should I say...)").

#### 4. Backend Functions

PHP function `mpu_get_shell_info($num)` is responsible for:

- Detecting if `shell` path is a file or folder.
- Scanning image files in the folder.
- Returning `shell_info` structure to frontend.

---

## CSS Position Adjustment

### Main CSS Selectors

#### 1. Ukagaka Shell Position (Entire Block)

```css
#ukagaka_shell {
    position: fixed;    /* Fixed to page */
    right: 0;          /* Align right */
    bottom: 20px;      /* Distance from bottom 20px */
    margin: 0 20px 0 0; /* Right margin 20px */
    z-index: 10000;    /* Z-index */
}
```

**How to Adjust:**

- `bottom`: Adjust vertical position (Increase to move up, decrease to move down).
- `right`: Adjust horizontal position (Increase to move left, decrease to move right).
- `margin`: Adjust margins.

#### 2. Ukagaka Image Container Position

```css
#ukagaka_img {
    margin-bottom: -10px; /* Vertical offset */
    /* margin-left: 30px; Add this property to move image right */
}
```

**How to Adjust:**

- `margin-bottom`: Adjust vertical position (Negative moves up, positive moves down).
- `margin-left`: Move image right (Add this and set value).
- `margin-right`: Move image left.

#### 3. Canvas Element Style

```css
#ukagaka_img canvas {
    opacity: 0.85; /* Opacity 85% */
}
```

**How to Adjust:**

- `opacity`: Opacity (0.0 fully transparent ~ 1.0 fully opaque).

#### 4. Balloon Position

```css
#ukagaka_msgbox {
    position: absolute;
    top: 50%;          /* Vertically center */
    left: -200px;      /* Offset left 200px (Display on left of Ukagaka) */
    transform: translateY(-50%); /* Vertical center adjustment */
}
```

**How to Adjust:**

- `left`: Adjust distance between balloon and Ukagaka (Negative moves left, positive moves right).
- `top`: Adjust vertical position.

#### 5. Main Container Padding

```css
#ukagaka {
    padding-right: 40px; /* Right padding, space for balloon */
}
```

**How to Adjust:**

- `padding-right`: Adjust right padding (Affects spacing between balloon and Ukagaka).

### Practical Adjustment Examples

#### Example 1: Move Ukagaka Right by 20px

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

#### Example 3: Adjust Vertical Position

```css
#ukagaka_img {
    margin-bottom: -20px; /* Move up more */
}
```

#### Example 4: Adjust Distance Between Balloon and Ukagaka

```css
#ukagaka_msgbox {
    left: -180px; /* Decrease distance (Closer to Ukagaka) */
}

/* Also adjust main container padding */
#ukagaka {
    padding-right: 30px; /* Decrease padding */
}
```

---

## FAQ

### Q: Animation is not playing?

**A:** Please check:

1. If all images are loaded completely (Check browser console for errors).
2. If the character is speaking (Animation only plays when speaking).
3. If the message is a system message (System messages do not trigger animation).

### Q: Image order is incorrect?

**A:** Please ensure image filenames use numeric sequences, for example:

- ✅ `frame1.png`, `frame2.png`, ..., `frame12.png`
- ✅ `001.png`, `002.png`, ..., `012.png`
- ❌ `frame_a.png`, `frame_b.png` (Alphabetical sort might be incorrect)

The system uses natural sort, so numbers will sort correctly.

### Q: How to go back to using `<img>` tag?

**A:** The Canvas feature has completely replaced the `<img>` tag, but single images are still supported. To use a single image, just enter the image file path (not folder) in the `shell` field.

### Q: Can I adjust animation playback speed?

**A:** Currently the frame interval is fixed at 100 ms/frame. To adjust, you can modify the `frameInterval` property in `ukagaka-anime.js`:

```javascript
frameInterval: 100, // Change to other value (Unit: ms)
```

### Q: CSS changes are not taking effect?

**A:** Please check:

1. If `!important` is being used to override your settings (Some themes might use it).
2. If browser cache is cleared.
3. If CSS selectors are correct.
4. If other CSS rules are overriding your settings.

---

## Related Files

- `ukagaka-anime.js` - Canvas Animation Manager
- `mpu_style.css` - Main Stylesheet
- `includes/ukagaka-functions.php` - `mpu_get_shell_info()` function
- `includes/frontend-functions.php` - HTML Generation and Canvas Initialization
- `ukagaka-core.js` - Animation Trigger Logic

---

## Update History

- **2.1.6** (2025-12-13) - Initial implementation of Canvas animation features

---

**Made with ❤ for WordPress**
