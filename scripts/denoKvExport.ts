// export_kv.ts
// deno-lint-ignore no-import-prefix
import { exportEntries } from "jsr:@deno/kv-utils";

// Replace with your database ID
const databaseId = "874b8d4a-a18e-440d-a819-37aa70240f9f";
const kvConnectUrl = `https://api.deno.com/databases/${databaseId}/connect`;

async function exportKvData() {
    const kv = await Deno.openKv(kvConnectUrl);
    const file = await Deno.open("export.ndjson", {
        write: true,
        create: true,
    });

    console.log("Exporting KV data...");

    // Exports all entries in the KV store.
    // You can also use { prefix: [...] } to export specific key ranges.
    for await (const chunk of exportEntries(kv, {prefix: []})) {
        await file.write(chunk);
    }

    file.close();
    kv.close();
    console.log("KV data successfully exported to export.ndjson");
}

exportKvData();
