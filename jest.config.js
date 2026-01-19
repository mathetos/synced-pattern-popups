module.exports = {
	preset: '@wordpress/jest-preset-default',
	testMatch: [ '**/tests/js/**/*.test.js' ],
	testEnvironment: 'jsdom',
	moduleNameMapper: {
		'^@/(.*)$': '<rootDir>/assets/js/$1',
	},
	collectCoverageFrom: [
		'assets/js/**/*.js',
		'!assets/js/**/*.min.js',
	],
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup.js' ],
};
