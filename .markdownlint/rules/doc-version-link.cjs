"use strict";

const path = require("node:path");
const { repositoryRoot, docIndex, walkTokens } = require("../lib/docs.cjs");

// A link into a *versioned* copy of these same docs. The docs in this repo are
// the source for whichever version they ship with, so pinning a version freezes
// the reader on an older release. `/docs/...` is deliberately absent: the site
// redirects it to the current version, and doc-link-target verifies it.
//
// tempestphp.com resolves 1.x, 2.x, 3.x, main, current, default and next;
// `latest` and `dev` resolve to nothing at all.
const SEGMENT = "\\d+\\.x|main|current|default|next|latest|dev";
const ROUTE = new RegExp(`^(?:https?://tempestphp\\.com)?/(${SEGMENT})(?:/(.*))?$`);

// The same thing written as a bare URL, which link syntax will not catch --
// it turns up in comments inside code samples.
const BARE = new RegExp(`https?://tempestphp\\.com/(${SEGMENT})(?:/\\S*)?`, "g");

module.exports = {
	names: ["TEMPEST005", "doc-version-link"],
	description: "Links must not pin a version of the docs site",
	tags: ["links", "tempest"],
	parser: "micromark",
	function: (params, onError) => {
		const root = repositoryRoot(params.name);
		const docsRoot = root ? path.join(root, "docs") : null;
		const index = docsRoot ? docIndex(docsRoot) : new Map();
		const directory = path.dirname(path.resolve(params.name));
		// One finding per URL per line: the same link is reachable both as a
		// token and as raw text, and a textual "is this already a link?" guard
		// mis-fires on titles and <...> targets.
		const found = new Map();

		/** Suggest the equivalent page in this repo, when one exists. */
		const suggest = (route) => {
			const [routePath] = (route || "").split("#");
			const target = index.get(routePath.replace(/\/$/, ""))
				?? index.get(`${routePath.replace(/\/$/, "")}.md`);
			return target ? `link to ${path.relative(directory, target)} instead` : null;
		};

		for (const token of walkTokens(params.parsers.micromark.tokens)) {
			if (token.type !== "resourceDestinationString"
				&& token.type !== "definitionDestinationString") {
				continue;
			}
			let text;
			try {
				text = decodeURI(token.text);
			} catch {
				text = token.text;
			}
			const match = ROUTE.exec(text);
			if (match) {
				const hint = suggest(match[2]);
				found.set(`${token.startLine}\u0000${text}`, {
					lineNumber: token.startLine,
					detail: `link is pinned to the ${match[1]} docs${hint ? `; ${hint}` : ""}`,
					context: token.text,
				});
			}
		}

		// Bare URLs anywhere in the file, code samples included.
		params.lines.forEach((line, offset) => {
			const lineNumber = offset + 1;
			for (const match of line.matchAll(BARE)) {
				const url = match[0].replace(/[.,);>]+$/, "");
				found.set(`${lineNumber}\u0000${url}`, {
					lineNumber,
					detail: `URL is pinned to the ${match[1]} docs; readers land on an older release`,
					context: url,
				});
			}
		});

		for (const problem of found.values()) {
			onError(problem);
		}
	},
};
