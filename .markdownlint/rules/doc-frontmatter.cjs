"use strict";

const REQUIRED = ["title", "description"];

module.exports = {
	names: ["TEMPEST003", "doc-frontmatter"],
	description: "Every doc needs frontmatter carrying a title and a description",
	tags: ["frontmatter", "tempest"],
	parser: "none",
	function: (params, onError) => {
		const frontMatter = params.frontMatterLines || [];

		if (frontMatter.length === 0) {
			onError({ lineNumber: 1, detail: "missing frontmatter block" });
			return;
		}

		const keys = new Set(
			frontMatter
				.map((line) => line.match(/^([A-Za-z0-9_-]+):\s*\S/)?.[1])
				.filter(Boolean),
		);

		for (const key of REQUIRED) {
			if (!keys.has(key)) {
				onError({
					// markdownlint adds the frontmatter offset, so this lands on the
					// first body line; the block itself cannot be pointed at from a rule.
					lineNumber: 1,
					detail: `frontmatter is missing \`${key}\`, or it has no value`,
				});
			}
		}
	},
};
