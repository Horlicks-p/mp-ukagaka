/**
 * MP Ukagaka JS Build Script
 * 
 * 功能：
 * 1. 合併核心 JS 檔案為 bundle
 * 2. 使用 Terser 壓縮
 * 
 * 用法：node build.js
 */

const fs = require('fs');
const path = require('path');
const { minify } = require('terser');

// 核心 JS 檔案（按依賴順序）
const coreFiles = [
    'ukagaka-base.js',
    'ukagaka-core.js',
    'ukagaka-anime.js',
    'ukagaka-emoji.js',
    'ukagaka-context.js',
    'ukagaka-greeting.js',
    'ukagaka-dialog.js',
    'ukagaka-chat-history.js',
    'ukagaka-chat-mode.js',
    'ukagaka-chat.js',
    'ukagaka-chat-wake.js',
    'ukagaka-features.js'
];

// 不合併的檔案（獨立 minify）
const standaloneFiles = [
    'ukagaka-textarearesizer.js'
];

const repoRoot = path.resolve(__dirname, '..', '..');
const jsDir = path.join(repoRoot, 'js');
const distDir = path.join(repoRoot, 'js', 'dist');

async function build() {
    let failed = false;

    console.log('🚀 MP Ukagaka JS Build Starting...\n');

    // 確保 dist 目錄存在
    if (!fs.existsSync(distDir)) {
        fs.mkdirSync(distDir, { recursive: true });
    }

    // === Phase 1: 合併核心檔案 ===
    console.log('📦 Phase 1: Building core bundle...');
    
    let bundleContent = `/**
 * MP Ukagaka Core Bundle
 * Generated: ${new Date().toISOString()}
 * 
 * 包含: ${coreFiles.join(', ')}
 */
`;

    for (const file of coreFiles) {
        const filePath = path.join(jsDir, file);
        if (fs.existsSync(filePath)) {
            const content = fs.readFileSync(filePath, 'utf-8');
            bundleContent += `\n// ========== ${file} ==========\n`;
            bundleContent += content;
            console.log(`  ✓ Added: ${file}`);
        } else {
            console.log(`  ✗ Not found: ${file}`);
        }
    }

    bundleContent += '\n';

    // 寫入未壓縮的 bundle
    const bundlePath = path.join(distDir, 'ukagaka-bundle.js');
    fs.writeFileSync(bundlePath, bundleContent);
    console.log(`  → Saved: js/dist/ukagaka-bundle.js (${(bundleContent.length / 1024).toFixed(1)} KB)\n`);

    // === Phase 2: Minify bundle ===
    console.log('🔧 Phase 2: Minifying bundle...');
    
    try {
        const minified = await minify(bundleContent, {
            compress: {
                drop_console: false, // 保留 console 以便調試
                drop_debugger: true,
                passes: 2
            },
            mangle: {
                reserved: [
                    // 保留全域變數
                    'mpuCanvasManager',
                    'mpuAjax',
                    'mpuFeatures',
                    'mpuChatManager',
                    'mpuAutoTalk',
                    'mpuTouch',
                    'mpuEmojiLoader',
                    'mpu_getCookie',
                    'mpu_setCookie',
                    'mpu_nextmsg'
                ]
            },
            format: {
                comments: false
            },
            sourceMap: false
        });

        const minBundlePath = path.join(distDir, 'ukagaka-bundle.min.js');
        fs.writeFileSync(minBundlePath, minified.code);
        
        const originalSize = bundleContent.length;
        const minifiedSize = minified.code.length;
        const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
        
        console.log(`  → Saved: js/dist/ukagaka-bundle.min.js (${(minifiedSize / 1024).toFixed(1)} KB)`);
        console.log(`  → Compression: ${savings}% reduction\n`);
    } catch (error) {
        console.error('  ✗ Minification failed:', error.message);
        failed = true;
    }

    // === Phase 3: Minify standalone files ===
    console.log('🔧 Phase 3: Minifying standalone files...');
    
    for (const file of standaloneFiles) {
        const filePath = path.join(jsDir, file);
        if (fs.existsSync(filePath)) {
            try {
                const content = fs.readFileSync(filePath, 'utf-8');
                const minified = await minify(content, {
                    compress: { drop_debugger: true },
                    mangle: true,
                    format: { comments: false }
                });
                
                const minFileName = file.replace('.js', '.min.js');
                const minFilePath = path.join(distDir, minFileName);
                fs.writeFileSync(minFilePath, minified.code);
                console.log(`  ✓ ${file} → ${minFileName}`);
            } catch (error) {
                console.log(`  ✗ Failed to minify ${file}: ${error.message}`);
                failed = true;
            }
        }
    }

    if (failed) {
        throw new Error('Build failed.');
    }

    console.log('\n✅ Build complete!');
}

build().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
