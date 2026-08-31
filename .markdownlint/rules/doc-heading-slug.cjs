"use strict";

const { headingIds } = require("../lib/docs.cjs");

module.exports = {
	names: ["TEMPEST006", "doc-heading-slug"],
	description: "Headings must produce distinct ids under the docs site's slug rule",
	tags: ["headings", "tempest"],
	parser: "none",
	function: (params, onError) => {
		const seen = new Map();
		// onError line numbers are relative to params.lines and markdownlint adds
		// the frontmatter offset itself -- but a line number quoted *inside* a
		// message is printed verbatim, so it has to be absolute already.
		const absolute = (line) => line + (params.frontMatterLines || []).length;

		for (const { id, line } of headingIds(params.lines)) {
			if (seen.has(id)) {
				// tempestphp.com emits the id verbatim with no -1/-2 suffixing, so two
				// headings with the same id produce two elements sharing one anchor.
				// For two headings of the same level this also collapses their entry
				// in the sidebar, which SubChapterExtractor keys by id.
				onError({
					lineNumber: line,
					detail: `heading id "${id}" is already used by the heading on line ${absolute(seen.get(id))}`,
				});
			} else {
				seen.set(id, line);
			}
		}
	},
};
