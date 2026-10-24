import fs from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {FileBlob, SpreadsheetFile} from '@oai/artifact-tool';
const base=new URL('.',import.meta.url);
const wb=await SpreadsheetFile.importXlsx(await FileBlob.load(fileURLToPath(new URL('rev9/ucetni-podklady.xlsx',base))));
const summary=await wb.inspect({kind:'workbook,sheet',maxChars:7000,tableMaxRows:1,tableMaxCols:3});
await fs.writeFile(new URL('workbook-inspection.ndjson',base),summary.ndjson);
const regions=[['Manifest','A1:B10'],['Penize','A1:F6'],['Doklady','H1:P4'],['Saldo','A1:G5'],['Vydaje','I1:O4'],['Sklad','E1:L5']];
for(const [sheetName,range] of regions){
 const image=await wb.render({sheetName,range,scale:1,format:'png'});
 await fs.writeFile(new URL('xlsx-'+sheetName+'.png',base),new Uint8Array(await image.arrayBuffer()));
 console.log(sheetName+' '+range+' rendered');
}
