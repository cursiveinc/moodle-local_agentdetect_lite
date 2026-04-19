/* eslint-env node */
// Build amd/src/*.js -> amd/build/*.min.js using Moodle's AMD convention.
// Transforms ES module imports to AMD define() with module ID injected,
// then minifies with terser. Matches the output format Moodle's Grunt
// pipeline produces so the files load correctly via RequireJS.

const fs = require('fs');
const path = require('path');
const babel = require('@babel/core');
const terser = require('terser');

const srcDir = path.join(__dirname, 'amd', 'src');
const buildDir = path.join(__dirname, 'amd', 'build');

const modules = fs.readdirSync(srcDir).filter((f) => f.endsWith('.js'));

(async () => {
    for (const filename of modules) {
        const modname = `local_agentdetect/${filename.replace(/\.js$/, '')}`;
        const srcPath = path.join(srcDir, filename);
        const source = fs.readFileSync(srcPath, 'utf8');

        // Transform ES modules to AMD, then terser-minify.
        const babelResult = babel.transformSync(source, {
            babelrc: false,
            configFile: false,
            sourceType: 'module',
            presets: [
                ['@babel/preset-env', {
                    targets: {browsers: ['last 2 versions', 'not dead']},
                    modules: 'amd',
                }],
            ],
            plugins: [
                ['babel-plugin-add-module-exports'],
            ],
            moduleId: modname,
            filename: srcPath,
        });

        const minified = await terser.minify(babelResult.code, {
            compress: {
                ecma: 2015,
                passes: 2,
            },
            mangle: true,
            format: {
                comments: false,
            },
        });

        if (minified.error) {
            throw minified.error;
        }

        const outPath = path.join(buildDir, filename.replace(/\.js$/, '.min.js'));
        fs.writeFileSync(outPath, minified.code);

        // Write an empty sourcemap file to satisfy Moodle's expectation.
        fs.writeFileSync(outPath + '.map', JSON.stringify({
            version: 3,
            file: path.basename(outPath),
            sources: [`../src/${filename}`],
            mappings: '',
            names: [],
        }));

        process.stdout.write(`built ${outPath} (${minified.code.length} bytes)\n`);
    }
})().catch((err) => {
    console.error(err);
    process.exit(1);
});
