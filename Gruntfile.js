'use strict';

module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [ '.' ]
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
	grunt.registerTask( 'fix', () => {
		grunt.config.set( 'eslint.options.fix', true );
		grunt.task.run( 'eslint' );
	} );
	grunt.registerTask( 'default', 'test' );
};
