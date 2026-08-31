"use strict";

const fs = require("node:fs");
const path = require("node:path");
const { repositoryRoot, fencedLines } = require("../lib/docs.cjs");

const SPAN = /\{b?`([^`]+)`[^}]*\}/g;

/** Tempest\Support\Str\to_kebab_case, as used by the docs site's renderer. */
function toKebabCase(value) {
	if (/^[a-z]+$/.test(value)) {
		return value;
	}
	return value
		.replace(/([a-z0-9])([A-Z])/g, "$1-$2")
		.replace(/([A-Z])([A-Z][a-z])/g, "$1-$2")
		.toLowerCase()
		.replace(/[^-\p{L}\p{N}\s]+/gu, "-")
		.replace(/\s+/g, "-")
		.replace(/-+/g, "-")
		.replace(/^-+|-+$/g, "");
}

/**
 * The path a {b`...`} span resolves to, per tempestphp.com's
 * src/Markdown/Extensions/GitHubLink/GitHubLinkToken.php. The renderer builds a
 * GitHub blob URL by *guessing* -- it never checks the file exists -- so a span
 * that maps to a missing file renders a 404 link with no error anywhere.
 */
function renderedPath(content) {
	let value = content;
	if (value.startsWith("#[")) {
		value = value.slice(2);
	}
	if (value.endsWith("]")) {
		value = value.slice(0, -1);
	}
	for (const prefix of ["\\Tempest\\", "Tempest\\"]) {
		if (value.startsWith(prefix)) {
			value = value.slice(prefix.length);
			break;
		}
	}
	return `${value
		.replace(/^(\w+)/, (segment) => `packages/${toKebabCase(segment)}/src`)
		.split("date-time").join("datetime")
		.replaceAll("\\", "/")}.php`;
}

module.exports = {
	names: ["TEMPEST002", "doc-reference-url"],
	description: "Every {b`...`} reference must render a GitHub link to a real file",
	tags: ["references", "tempest"],
	parser: "micromark",
	function: (params, onError) => {
		const root = repositoryRoot(params.name);
		if (!root) {
			// Silently checking nothing is indistinguishable from clean docs.
			onError({
				lineNumber: 1,
				detail: "cannot locate the repository root (no composer.json above this file); references were not checked",
			});
			return;
		}

		// Token lines and params.lines share one coordinate space; markdownlint
		// applies the frontmatter offset itself when reporting.
		const fenced = fencedLines(params);

		params.lines.forEach((line, index) => {
			const lineNumber = index + 1;
			if (fenced.has(lineNumber)) {
				return;
			}
			for (const match of line.matchAll(SPAN)) {
				// Not trimmed: the renderer does not trim either, so a stray space
				// really does produce a 404 link.
				const content = match[1];
				const relative = renderedPath(content);
				if (!fs.existsSync(path.join(root, relative))) {
					onError({
						lineNumber,
						detail: `renders a GitHub link to a missing file: ${relative}`,
						context: match[0],
					});
				}
			}
		});
	},
};
