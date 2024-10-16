module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended', 'prettier' ],
	env: {
		browser: true,
		node: true,
		es6: true,
	},
	parserOptions: {
		ecmaVersion: 2021,
		sourceType: 'module',
	},
	rules: {
		// Allow spaces inside object, array, and round brackets
		'object-curly-spacing': ['error', 'always'], // Require space inside object braces `{ key: value }`
		'array-bracket-spacing': ['error', 'always'], // Require space inside array brackets `[ value ]`
		'space-in-parens': ['error', 'always'], // Require space inside round brackets `( value )`

		// Allow flexible indentation and whitespace
		'indent': ['error', 'tab', { 'SwitchCase': 1 }],
		'no-mixed-spaces-and-tabs': 'off', // Allow mixing spaces and tabs
		'no-trailing-spaces': 'off', // Allow trailing spaces

		// Other useful rules for clean code
		'no-console': 'warn', // Warn about console.log usage
		'quotes': ['error', 'single'], // Enforce single quotes
		'comma-dangle': ['error', 'always-multiline'], // Trailing commas in multiline
		'no-unused-vars': 'warn', // Warn about unused variables
	},
};
