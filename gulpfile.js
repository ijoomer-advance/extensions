var gulp = require('gulp'),

// ZIP compress files
zip = require('gulp-zip'),

// Utility functions for gulp plugins
gutil = require('gulp-util')
//notify = require("gulp-notify"),

// File systems
fs = require('fs'),
path = require('path'),
merge = require('merge-stream'),

// Gulp Configuration
config = require('./gulp-config.json')

// iJoomer Extension Configuration file
extensionConfig = require('./package.json');

function getFolders(dir) {
    return fs.readdirSync(dir)
      .filter(function(file) {
        return fs.statSync(path.join(dir, file)).isDirectory();
      });
}

// Creating zip files for iJoomer Extensions
gulp.task('release', function() {

	var folders = getFolders(config.repoDir);

	gutil.log(gutil.colors.red('Following ' + folders.length + ' extensions for release'));

	var tasks = folders.map(function(folder) {

		gutil.log(gutil.colors.blue(folder));

		return gulp.src(path.join(config.repoDir, folder, '**'))
		    .pipe(zip(extensionConfig.name + '_' + folder + '_' + 'v' + config.version + '.zip'))
			.pipe(gulp.dest(config.packageDir));

			//.pipe(notify("Preparing: " + folder + " on <%= new Date().toDateString() %>"))
	});

	return merge(tasks);
});

gulp.task('default', function() {
	// Default task
});
