"use strict";

const fs = require("node:fs");
const path = require("node:path");
const { slug, headingSlugs, repositoryRoot, docIndex, walkTokens } = require("../lib/docs.cjs");

const EXTERNAL = /^(https?:|mailto:|tel:)/;

/** Resolve a relative link the way the docs are authored: with or without `.md`. */
function resolve(target, fromDirectory) {
	for (const candidate of [target, `${target}.md`]) {
		const absolute = path.resolve(fromDirectory, candidate);
		if (fs.existsSync(absolute) && fs.statSync(absolute).isFile()) {
			return absolute;
		}
	}
	return null;
}

module.exports = {
	names: ["TEMPEST001", "doc-link-target"],
	description: "Relative doc links must resolve and #anchors must match a heading",
	tags: ["links", "tempest"],
	parser: "micromark",
	function: (params, onError) => {
		const self = path.resolve(params.name);
		const directory = path.dirname(self);
		const root = repositoryRoot(params.name);
		const docsRoot = root ? path.join(root, "docs") : null;
		const index = docsRoot ? docIndex(docsRoot) : new Map();

		/** Look a path up in its website form, with ordering prefixes stripped. */
		const fromIndex = (candidate) =>
			index.get(candidate) ?? index.get(`${candidate}.md`) ?? null;

		for (const token of walkTokens(params.parsers.micromark.tokens)) {
			// `resource...` is an inline link or image; `definition...` is the
			// `[label]: target` form, which emits a different token type entirely.
			if (token.type !== "resourceDestinationString"
				&& token.type !== "definitionDestinationString") {
				continue;
			}

			// decodeURI throws URIError on a malformed escape, and markdownlint does
			// not catch it -- an unescaped `%` would abort the run for every file.
			let target;
			try {
				target = decodeURI(token.text);
			} catch {
				target = token.text;
			}
			const lineNumber = token.startLine;

			if (EXTERNAL.test(target)) {
				continue; // liveness of external URLs is not ours to verify
			}

			const [filePart, anchor] = target.split("#");

			// A site-absolute path: only /docs/... names a file we can verify.
			// Other routes (/discord, /blog/...) are not docs and are skipped.
			if (filePart.startsWith("/")) {
				if (!filePart.startsWith("/docs/")) {
					continue;
				}
				const page = fromIndex(filePart.slice("/docs/".length));
				if (!page) {
					onError({ lineNumber, detail: `no doc matches ${filePart}`, context: token.text });
				} else if (anchor && !headingSlugs(page).has(slug(anchor))) {
					onError({
						lineNumber,
						detail: `anchor #${anchor} matches no heading in ${path.basename(page)}`,
						context: token.text,
					});
				}
				continue;
			}

			// An anchor with no path points at a heading in this same file.
			if (!filePart) {
				if (anchor && !headingSlugs(self).has(slug(anchor))) {
					onError({
						lineNumber,
						detail: `anchor #${anchor} matches no heading in this file`,
						context: target,
					});
				}
				continue;
			}

			// Relative links are authored both as real paths and in website form
			// (`../extra-topics/contributing`), so fall back to the index.
			const resolved = resolve(filePart, directory)
				?? (docsRoot ? fromIndex(path.relative(docsRoot, path.resolve(directory, filePart))) : null);
			if (!resolved) {
				onError({
					lineNumber,
					detail: `target does not exist: ${filePart}`,
					context: target,
				});
				continue;
			}

			// Site-absolute /docs/... links correctly omit the extension; relative
			// ones include it everywhere else in the docs. Only extensionless paths
			// qualify -- an image or asset link is not a doc missing its suffix.
			if (!/\.\w+$/.test(filePart)) {
				onError({
					lineNumber,
					detail: `link is missing the .md extension: ${filePart}`,
					context: target,
				});
			}

			if (anchor && !headingSlugs(resolved).has(slug(anchor))) {
				onError({
					lineNumber,
					detail: `anchor #${anchor} matches no heading in ${path.basename(resolved)}`,
					context: target,
				});
			}
		}
	},
};
