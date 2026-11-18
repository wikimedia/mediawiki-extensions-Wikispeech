'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

// Directories with their own config, which other configs don't apply to.
// These were directories with `"root": true` in .eslintrc.json.
const rootDirs = {
	modules: [ 'modules/**' ],
	tests: [ 'tests/qunit/**' ]
};

const modulesRules = {
	'no-jquery/no-done-fail': 'warn'
};

module.exports = defineConfig( [
	globalIgnores( [
		// Build
		'vendor/',
		'docs/',

		// Language files written automatically by TranslateWiki
		'**/i18n/**/*.json',
		'!**/i18n/**/en.json',
		'!**/i18n/**/qqq.json'
	] ),

	// Gruntfile.js, etc.
	{
		ignores: Object.values( rootDirs ).flat(),
		extends: [ wikimedia( 'server' ) ]
	},

	// Code which runs in MediaWiki
	{
		files: rootDirs.modules,
		extends: [ wikimedia( 'client', 'jquery', 'mediawiki' ) ],
		rules: modulesRules
	},

	// QUnit tests
	{
		files: rootDirs.tests,
		extends: [ wikimedia( 'client', 'jquery', 'mediawiki', 'qunit' ) ],
		languageOptions: {
			globals: {
				sinon: 'readonly'
			}
		},
		rules: {
			...modulesRules,
			'no-jquery/no-global-selector': 'off'
		}
	}
] );
