"use strict";

const { walkTokens } = require("../lib/docs.cjs");

// Languages the docs may tag a fence with, matched case-sensitively because the
// highlighter is. Note `txt` and `ellison` are NOT registered on tempestphp.com's
// highlighter, so those fences fall back to PhpLanguage and render as PHP; they
// are allowed here because the fix belongs on the site, not in the docs.
const LANGUAGES = new Set([
	"php", "html", "js", "ts", "css", "json", "sh", "console", "sql",
	"txt", "md", "env", "yaml", "yml", "xml", "diff", "ini", "blade",
	"ellison", "json_extended",
]);

// Aliases that work but make the docs inconsistent, mapped to the spelling the
// rest of the docs use.
const ALIASES = {
	bash: "sh",
	shell: "sh",
	dotenv: "env",
	javascript: "js",
	typescript: "ts",
};

module.exports = {
	names: ["TEMPEST004", "doc-fence-language"],
	description: "Code fences must carry a known, consistently spelled language",
	tags: ["code", "tempest"],
	parser: "micromark",
	function: (params, onError) => {
		for (const token of walkTokens(params.parsers.micromark.tokens)) {
			if (token.type !== "codeFenced") {
				continue;
			}

			const lineNumber = token.startLine;
			const info = [...walkTokens(token.children)]
				.find((child) => child.type === "codeFencedFenceInfo");

			if (!info) {
				onError({ lineNumber, detail: "code fence has no language" });
				continue;
			}

			// tempest/highlight appends line ranges, as in ```php{1,10}.
			const language = info.text.split(/\s+/)[0].replace(/\{.*\}$/, "");
			const lowercased = language.toLowerCase();

			if (LANGUAGES.has(language)) {
				continue;
			}

			if (ALIASES[lowercased]) {
				onError({
					lineNumber,
					detail: `code fence language \`${language}\` is inconsistent; the rest of the docs use \`${ALIASES[lowercased]}\``,
				});
			} else if (LANGUAGES.has(lowercased)) {
				// Highlighter::parse does `$this->languages[$language] ?? null` -- a
				// case-sensitive lookup -- and falls back to PhpLanguage, so ```PHP
				// and ```JSON silently render as PHP.
				onError({
					lineNumber,
					detail: `code fence language \`${language}\` must be lowercase; the highlighter matches case-sensitively and falls back to PHP`,
				});
			} else {
				onError({
					lineNumber,
					detail: `unknown code fence language \`${language}\`; add it to LANGUAGES in this rule if intentional`,
				});
			}
		}
	},
};
