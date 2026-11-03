#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p .local
work="$(mktemp -d "$PWD/.local/blueprints.XXXXXX")"
trap 'rm -rf "$work"' EXIT
cp dist/collage.zip "$work/"
curl -fsSL https://playground.wordpress.net/blueprint-schema.json -o "$work/schema.json"

for blueprint in playground/*.json; do
    npx --yes --package=ajv-cli@5.0.0 ajv validate --strict=false -s "$work/schema.json" -d "$blueprint"
    local_blueprint="$work/$(basename "$blueprint")"
    # Exercise the exact release ZIP without requiring a release to exist yet.
    node --input-type=module - "$blueprint" "$local_blueprint" <<'JS'
import { readFileSync, writeFileSync } from 'node:fs';
const blueprint = JSON.parse(readFileSync(process.argv[2], 'utf8'));
blueprint.steps.find(step => step.step === 'installPlugin').pluginData = {
    resource: 'bundled', path: 'collage.zip',
};
blueprint.steps.push({
    step: 'runPHP',
    code: `<?php
require '/wordpress/wp-load.php';
wp_set_current_user(1);
foreach (['docx', 'markdown', 'html'] as $sample) {
    $request = new WP_REST_Request('POST', '/pandoc-importer/v1/convert');
    $request->set_param('sample', $sample);
    $response = rest_do_request($request);
    if ($response->get_status() !== 200 || empty($response->get_data()['blocks'])) {
        throw new RuntimeException($sample . ': ' . wp_json_encode($response->get_data()));
    }
}
echo 'Collage sample conversions passed.';
`,
});
writeFileSync(process.argv[3], JSON.stringify(blueprint));
JS
    npx --yes --package=@wp-playground/cli@3.1.62 wp-playground-cli run-blueprint \
        --blueprint="$local_blueprint" --blueprint-may-read-adjacent-files
done

npx --yes --package=ajv-cli@5.0.0 ajv validate --strict=false \
    -s "$work/schema.json" -d .wordpress-org/blueprints/blueprint.json
npx --yes --package=@wp-playground/cli@3.1.62 wp-playground-cli run-blueprint \
    --blueprint="$work/sample-draft.json" --blueprint-may-read-adjacent-files --php=8.2 --wp=6.7
