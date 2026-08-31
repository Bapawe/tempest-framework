"use strict";

const fs = require("node:fs");
const path = require("node:path");

/**
 * The docs site's heading id rule, from tempestphp.com's
 * src/Markdown/Extensions/Heading/HeadingToken.php:
 *
 *     trim() |> strtolower() |> str_replace(' ', '-')
 *
 * Every other character is kept verbatim -- backticks and slashes included --
 * so `## \`tempest/view\`` has the id `` `tempest/view` ``. Do not "improve"
 * this into a conventional slug; it would stop matching the rendered pages.
 *
 * strtolower() is ASCII-only, so `## Événements` keeps its accented capital.
 * String.toLowerCase() is not, which is why this maps [A-Z] by hand.
 */
const slug = (text) => text.trim().replace(/[A-Z]/g, (c) => c.toLowerCase()).replaceAll(" ", "-");

/**
 * Heading ids in document order, as the site's HeadingRule produces them:
 * `comesNext('#')` with an unbounded `strspn($buffer, '#')`, so a space after
 * the hashes is optional, seven hashes are fine, and trailing `##` is part of
 * the text rather than a closing marker. Fenced blocks are skipped -- the
 * site's PreRule consumes them before HeadingRule ever sees a `#` comment.
 */
function headingIds(lines) {
	const ids = [];
	let fence = null;

	lines.forEach((line, index) => {
		const fenceMatch = /^\s*(`{3,}|~{3,})(.*)$/.exec(line);
		if (fence !== null) {
			if (fenceMatch && fenceMatch[1][0] === fence[0]
				&& fenceMatch[1].length >= fence.length && !fenceMatch[2].trim()) {
				fence = null;
			}
			return;
		}
		if (fenceMatch) {
			fence = fenceMatch[1];
			return;
		}
		const heading = /^(#+)(.*)$/.exec(line);
		if (heading) {
			ids.push({ id: slug(heading[2]), line: index + 1 });
		}
	});

	return ids;
}

const headingCache = new Map();

/** Every heading id declared by a doc, using the site's rule. */
function headingSlugs(absolutePath) {
	if (!headingCache.has(absolutePath)) {
		let ids = [];
		try {
			ids = headingIds(fs.readFileSync(absolutePath, "utf8").split("\n"));
		} catch {
			// Unreadable target. Callers resolve the path first, so this is a race
			// or a permissions problem rather than a broken link; an empty set makes
			// every anchor look valid, so surface nothing rather than false errors.
		}
		headingCache.set(absolutePath, new Set(ids.map((entry) => entry.id)));
	}
	return headingCache.get(absolutePath);
}

const rootCache = new Map();

/** Walk up from a doc to the repository root (the directory holding composer.json). */
function repositoryRoot(fromPath) {
	// Resolve first: rules receive relative paths when invoked through a glob,
	// and a relative walk stops at "." before it can test the root itself.
	let current = path.dirname(path.resolve(fromPath));
	if (rootCache.has(current)) {
		return rootCache.get(current);
	}
	const start = current;
	for (;;) {
		if (fs.existsSync(path.join(current, "composer.json"))) {
			rootCache.set(start, current);
			return current;
		}
		const parent = path.dirname(current);
		if (parent === current) {
			break;
		}
		current = parent;
	}
	rootCache.set(start, null);
	return null;
}

/** Line ranges covered by fenced code blocks, so prose-only checks can skip them. */
function fencedLines(params) {
	const fenced = new Set();
	const walk = (tokens) => {
		for (const token of tokens) {
			if (token.type === "codeFenced") {
				for (let line = token.startLine; line <= token.endLine; line++) {
					fenced.add(line);
				}
			}
			if (token.children) {
				walk(token.children);
			}
		}
	};
	walk(params.parsers.micromark.tokens);
	return fenced;
}

/** Depth-first walk over the micromark token tree. */
function* walkTokens(tokens) {
	for (const token of tokens) {
		yield token;
		if (token.children) {
			yield* walkTokens(token.children);
		}
	}
}

const indexCache = new Map();

/** Strip a `3-` / `03-` ordering prefix, which the site drops from its URLs. */
const stripOrdering = (name) => name.replace(/^\d+[-_]/, "");

/**
 * Every plausible spelling of a doc path mapped to the file on disk: the real
 * relative path, the same without `.md`, and the *website* form with ordering
 * prefixes removed. The site routes `/docs/extra-topics/standalone-components`
 * at `docs/5-extra-topics/02-standalone-components.md`, so links are authored
 * both ways and both have to resolve.
 */
function docIndex(docsRoot) {
	if (indexCache.has(docsRoot)) {
		return indexCache.get(docsRoot);
	}
	const index = new Map();
	const walk = (directory) => {
		for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
			const absolute = path.join(directory, entry.name);
			if (entry.isDirectory()) {
				walk(absolute);
			} else if (entry.name.endsWith(".md")) {
				const relative = path.relative(docsRoot, absolute);
				const parts = relative.split(path.sep);
				const clean = parts.slice(0, -1).map(stripOrdering)
					.concat(stripOrdering(parts.at(-1)).replace(/\.md$/, ""))
					.join("/");
				for (const key of [relative, relative.replace(/\.md$/, ""), clean]) {
					if (!index.has(key)) {
						index.set(key, absolute);
					}
				}
			}
		}
	};
	try {
		walk(docsRoot);
	} catch {
		// no docs/ directory; the index stays empty
	}
	indexCache.set(docsRoot, index);
	return index;
}

module.exports = { slug, headingIds, headingSlugs, repositoryRoot, docIndex, fencedLines, walkTokens };
