/* Re-indent the signals tab body in report.php. Boundary: the unique line
   pair "} else {" + next line "    // Flags tab." which is the tab switch. */
const fs = require('fs');
const path = 'report.php';
let lines = fs.readFileSync(path, 'utf8').split(/\r?\n/);

const start = lines.findIndex((l) => l.trim() === "if ($tab === 'signals') {");
if (start < 0) throw new Error('signals block not found');

let end = -1;
for (let i = start + 1; i < lines.length - 1; i++) {
    if (lines[i].trim() === '} else {' && lines[i + 1].trim() === '// Flags tab.') {
        end = i;
        break;
    }
}
if (end < 0) throw new Error('Flags tab boundary not found');

console.log(`signals block: if at line ${start + 1}, } else { at line ${end + 1}`);

for (let i = start + 1; i < end; i++) {
    if (lines[i].trim() !== '') lines[i] = '    ' + lines[i];
}

// Remove blank line right after the opening brace.
if (lines[start + 1].trim() === '') {
    lines.splice(start + 1, 1);
    end--;
}
// Remove blank lines right before the "} else {".
while (lines[end - 1].trim() === '') {
    lines.splice(end - 1, 1);
    end--;
}

fs.writeFileSync(path, lines.join('\r\n'));
console.log('done');
