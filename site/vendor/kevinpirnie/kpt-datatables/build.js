/**
 * KPT DataTables Build Script
 *
 * Concatenates and minifies JavaScript files into a single bundle.
 * Minifies individual theme CSS files alongside their source versions.
 *
 * Usage:
 *   node build.js all    - Build both JS and CSS
 *   node build.js js     - Build JS only
 *   node build.js css    - Build CSS only
 *
 * @since   1.2.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

const fs = require('fs');
const path = require('path');
const { minify } = require('terser');
const CleanCSS = require('clean-css');

// === Configuration ===

/**
 * JavaScript source files in load order
 * theme-helpers.js must load before datatables.js
 */
const jsFiles = [
    'src/assets/js/theme-helpers.js',
    'src/assets/js/datatables.js'
];

/**
 * Output paths for concatenated and minified JS bundle
 */
const jsOutputDir = 'src/assets/js/dist';
const jsBundleName = 'kpt-datatables.min.js';

/**
 * CSS theme files to minify individually
 * Source files are preserved, minified versions get dist/*.min.css extension
 */
const cssThemeDir = 'src/assets/css/themes';
const cssOutputDir = 'src/assets/css/dist';
const cssFiles = ['plain.css', 'uikit.css', 'bootstrap.css', 'tailwind.css'];

// === Build Functions ===

/**
 * Concatenate and minify JavaScript files into a single bundle
 *
 * Reads all JS source files in order, concatenates them with
 * source file comment headers, then minifies using Terser.
 * Outputs both the minified bundle to the dist directory.
 *
 * @return {Promise<void>}
 */
async function buildJs() {
    console.log('Building JavaScript bundle...');

    // Ensure output directory exists
    if (!fs.existsSync(jsOutputDir)) {
        fs.mkdirSync(jsOutputDir, { recursive: true });
    }

    // Concatenate all source files with headers
    let concatenated = '';
    concatenated += '/**\n';
    concatenated += ' * KPT DataTables - Bundled JavaScript\n';
    concatenated += ` * Built: ${new Date().toISOString()}\n`;
    concatenated += ' * \n';
    concatenated += ' * @author  Kevin Pirnie <me@kpirnie.com>\n';
    concatenated += ' * @package KPT/DataTables\n';
    concatenated += ' */\n\n';

    for (const file of jsFiles) {
        if (!fs.existsSync(file)) {
            console.error(`  ERROR: File not found: ${file}`);
            process.exit(1);
        }

        const source = fs.readFileSync(file, 'utf8');
        const fileName = path.basename(file);

        concatenated += `/* === ${fileName} === */\n`;
        concatenated += source;
        concatenated += '\n\n';

        console.log(`  Added: ${file}`);
    }

    // Minify with Terser
    try {
        const minified = await minify(concatenated, {
            compress: {
                dead_code: true,
                drop_console: false,
                passes: 2
            },
            mangle: {
                reserved: [
                    'DataTablesJS',
                    'KPDataTablesPlain',
                    'KPDataTablesBootstrap'
                ]
            },
            format: {
                comments: /^!/,
                preamble: '/*! KPT DataTables | MIT License | Kevin Pirnie */'
            }
        });

        const outputPath = path.join(jsOutputDir, jsBundleName);
        fs.writeFileSync(outputPath, minified.code);

        // Report file sizes
        const originalSize = Buffer.byteLength(concatenated, 'utf8');
        const minifiedSize = Buffer.byteLength(minified.code, 'utf8');
        const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);

        console.log(`  Output: ${outputPath}`);
        console.log(`  Original: ${formatBytes(originalSize)} -> Minified: ${formatBytes(minifiedSize)} (${savings}% reduction)`);
        console.log('  JS build complete.\n');
    } catch (error) {
        console.error('  Terser minification failed:', error.message);
        process.exit(1);
    }
}

/**
 * Minify individual CSS theme files
 *
 * Reads each theme CSS file and creates a minified .min.css
 * version in the dist directory using clean-css.
 * Skips the Tailwind source file (tailwind.src.css) as it
 * requires Tailwind compilation first.
 *
 * @return {void}
 */
function buildCss() {
    console.log('Minifying CSS theme files...');

    // Ensure output directory exists
    if (!fs.existsSync(cssOutputDir)) {
        fs.mkdirSync(cssOutputDir, { recursive: true });
    }

    const cleanCss = new CleanCSS({
        level: 2,
        format: {
            breakWith: 'lf'
        }
    });

    for (const file of cssFiles) {
        const sourcePath = path.join(cssThemeDir, file);

        if (!fs.existsSync(sourcePath)) {
            console.warn(`  SKIP: File not found: ${sourcePath}`);
            continue;
        }

        const source = fs.readFileSync(sourcePath, 'utf8');
        const minified = cleanCss.minify(source);

        if (minified.errors.length > 0) {
            console.error(`  ERROR minifying ${file}:`, minified.errors);
            continue;
        }

        // Write minified version to dist directory
        const minFileName = file.replace('.css', '.min.css');
        const outputPath = path.join(cssOutputDir, minFileName);

        const header = `/*! KPT DataTables ${file.replace('.css', '')} theme | MIT License | Kevin Pirnie */\n`;
        fs.writeFileSync(outputPath, header + minified.styles);

        // Report file sizes
        const originalSize = Buffer.byteLength(source, 'utf8');
        const minifiedSize = Buffer.byteLength(minified.styles, 'utf8');
        const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);

        console.log(`  ${file} -> dist/${minFileName} (${formatBytes(originalSize)} -> ${formatBytes(minifiedSize)}, ${savings}% reduction)`);

        // Report warnings if any
        if (minified.warnings.length > 0) {
            minified.warnings.forEach(w => console.warn(`    Warning: ${w}`));
        }
    }

    console.log('  CSS build complete.\n');
}

/**
 * Format byte count to human-readable string
 *
 * @param  {number} bytes Byte count
 * @return {string} Formatted string (e.g., "12.5 KB")
 */
function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// === Main Execution ===

const target = process.argv[2] || 'all';

console.log(`\nKPT DataTables Build (${target})\n${'='.repeat(40)}\n`);

(async () => {
    switch (target) {
        case 'js':
            await buildJs();
            break;
        case 'css':
            buildCss();
            break;
        case 'all':
            await buildJs();
            buildCss();
            break;
        default:
            console.error(`Unknown target: ${target}`);
            console.log('Usage: node build.js [all|js|css]');
            process.exit(1);
    }

    console.log('Build finished.');
})();