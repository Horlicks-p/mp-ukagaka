---
description: Environment setup and paths for this project
---

# Environment Configuration

## Node.js Path
Node.js is installed at a custom location: `C:\D\node.js`

When running Node.js or npm commands, use the full path if needed:
- Node: `C:\D\node.js\node.exe`
- npm: `C:\D\node.js\npm.cmd`
- npx: `C:\D\node.js\npx.cmd`

## Other Tools
- PHP: `C:\D\php\php\php.exe`
- Git Bash: `C:\D\Git\git-bash.exe`
- Git Cmd: `C:\D\Git\git-cmd.exe`

## Project Paths
- Plugin root: `c:\D\php\mp-ukagaka`
- JS source files: `c:\D\php\mp-ukagaka\js\`
- JS bundle output: `c:\D\php\mp-ukagaka\js\dist\`

## Build Commands

// turbo-all
To rebuild the JS bundle (production, minified):
```bash
PATH="/c/D/node.js:$PATH" /c/D/node.js/npx.cmd esbuild ukagaka-bundle-entry.js --bundle --outfile=dist/ukagaka-bundle.min.js --format=iife --target=es2017 --minify
```

To rebuild the JS bundle (development, unminified):
```bash
PATH="/c/D/node.js:$PATH" /c/D/node.js/npx.cmd esbuild ukagaka-bundle-entry.js --bundle --outfile=dist/ukagaka-bundle.js --format=iife --target=es2017
```

