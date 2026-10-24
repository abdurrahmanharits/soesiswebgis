// Generates src/shared/api/schema.ts from the backend contract (../contracts/openapi.json).
//   node scripts/api-types.mjs          write the file
//   node scripts/api-types.mjs --check  fail if the committed file is out of date
import { readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import openapiTS, { astToString } from "openapi-typescript";

const contract = new URL("../../contracts/openapi.json", import.meta.url);
const target = new URL("../src/shared/api/schema.ts", import.meta.url);

const header = "// Generated from contracts/openapi.json by `npm run api:generate`. Do not edit.\n\n";
const generated = header + astToString(await openapiTS(contract));

if (process.argv.includes("--check")) {
  const current = await readFile(target, "utf8").catch(() => "");
  if (current !== generated) {
    console.error(`${fileURLToPath(target)} is out of date with contracts/openapi.json. Run: npm run api:generate`);
    process.exit(1);
  }
  console.log("API types match contracts/openapi.json.");
} else {
  await writeFile(target, generated);
  console.log(`Wrote ${fileURLToPath(target)}`);
}
