/*jshint node:true */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'*.js',
				'modules/**/*.js',
				'tests/**/*.js'
			]
		},
		jscs: {
			src: '<%= jshint.all %>'
		},
		banana: conf.MessagesDirs,
		jsonlint: {
			all: [
				'*.json',
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		stylelint: {
			options: {
				formatter: 'string',
				ignoreDisables: false,
				failOnError: true,
				outputFile: '',
				reportNeedlessDisables: false,
				syntax: ''
			},
			all: 'modules/**/*.{css,less}'
		}
	} );

	grunt.registerTask(
		'test',
		[
			'jshint',
			'jscs',
			'jsonlint',
			'banana',
			'stylelint'
		]
	);
	grunt.registerTask( 'default', 'test' );
};
