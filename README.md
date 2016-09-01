# We are not maitaining new version in this repository anymore. To download latest versions please visit [iJoomer](http://www.iJoomer.com)

iJoomer Advance Joomla! Extensions   [![Build Status](https://travis-ci.org/ijoomer-advance/extensions.svg)](https://travis-ci.org/ijoomer-advance/extensions)
=============================

Native iJoomer Extensions for iJoomer Joomla! Component.
For more details http://www.iJoomer.com

### Install
#### To install all the dependencies,

```
sudo npm install
```

### Using Gulp build system
#### Configuration file `gulp-config.json`

Copy and change default information given in sample config file.

#### Following tasks and switches are available:

This command is to release the extensions.

    gulp release


This command will read the base directory and create zip files for each of the folder.

#### === Switches ===
Pass an argument to choose different folder

    --folder {source direcory}  Default: "./plugins"

Pass an argument to change suffix for extension

    --suffix {text of suffix}   Default: "plg_"

#### Example Usage:

	gulp release:extensions --folder ./modules --suffix ext_
