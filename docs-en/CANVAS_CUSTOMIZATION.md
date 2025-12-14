# Canvas Animation & CSS Customization Guide

> 🎨 Explains how to implement MP Ukagaka's Canvas animation features and how to adjust Ukagaka positioning via CSS

---

## 📑 Table of Contents

1. [Canvas Animation Features Introduction](#canvas-animation-features-introduction)
2. [Animation Setup](#animation-setup)
3. [Technical Implementation Details](#technical-implementation-details)
4. [CSS Position Adjustment](#css-position-adjustment)
5. [FAQ](#faq)

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
