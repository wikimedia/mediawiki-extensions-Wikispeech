/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'*.js',
				'modules/**/*.js',
				'tests/**/*.js',
				'**/*.json',
				'!node_modules/**',
				'!vendor/**',
				'!docs/**'
			]
		},
		banana: conf.MessagesDirs,
		stylelint: {
			options: {
				formatter: 'string',
				ignoreDisables: false,
				failOnError: true,
				outputFile: '',
				reportNeedlessDisables: true,
				syntax: '',
				cache: true
			},
			all: 'modules/**/*.{css,less}'
		}
	} );

	grunt.registerTask(
		'test',
		[
			'eslint',
			'banana',
			'stylelint'
		]
	);
	grunt.registerTask( 'fix', function () {
		grunt.config.set( 'eslint.options.fix', true );
		grunt.task.run( 'eslint' );
	} );
	grunt.registerTask( 'default', 'test' );
};
