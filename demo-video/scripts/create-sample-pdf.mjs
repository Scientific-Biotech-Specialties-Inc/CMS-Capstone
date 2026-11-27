import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

function buildValidPdf(lines) {
  const esc = (s) => s.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
  const textOps = lines
    .map((line, i) => `BT /F1 ${i === 0 ? 15 : 11} Tf 56 ${740 - i * 22} Td (${esc(line)}) Tj ET`)
    .join('\n');

  const objects = [
    '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n',
    '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n',
    '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n',
    `4 0 obj\n<< /Length ${Buffer.byteLength(textOps, 'utf8')} >>\nstream\n${textOps}\nendstream\nendobj\n`,
    '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n',
  ];

  let body = '%PDF-1.4\n';
  const offsets = [0];
  for (const obj of objects) {
    offsets.push(Buffer.byteLength(body, 'utf8'));
    body += obj;
  }
  const xrefPos = Buffer.byteLength(body, 'utf8');
  let xref = `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
  for (let i = 1; i < offsets.length; i++) {
    xref += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
  }
  const trailer = `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefPos}\n%%EOF\n`;
  return Buffer.from(body + xref + trailer, 'utf8');
}

const pdf = buildValidPdf([
  'EQUIPMENT LEASE AND REAGENT SUPPLY AGREEMENT',
  'Contract Reference: SBSI-2026-DEMO-042',
  'Business Partner: St. Luke\'s Medical Center',
  'Category: Equipment Lease',
  'Region: Luzon | SBSI Branch: Main Office',
  'Start Date: 2026-12-01 | End Date: 2027-11-30',
  'Item Code: ITM-0842 | Serial No: SN-2026-0842 | SBU: SBU-003',
  'Scope: Automated clinical hematology analyzer lease, reagent consumables,',
  'calibration, preventive maintenance, and 24-hour technical SLA support.',
  'Compliance: PH FDA IVD registration and Data Privacy Act (RA 10173).',
]);

const target = resolve(process.cwd(), 'scripts/sample-sbsi-contract.pdf');
writeFileSync(target, pdf);
console.log('Generated sample PDF:', target, pdf.length, 'bytes');
