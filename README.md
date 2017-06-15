# bitsquare_market
A simple web interface to view bitsquare markets.

This is a bare-bones implementation that reads the JSON files created by bitsquare
and publishes an API for web clients to access them.

# Performance, or lack thereof.

At present, no database is used and backend operations are very inefficient.

This is OK for the moment as there is little bitsquare data, but the implementation
will need to be optimized when bitsquare volume picks up.

I have separated the data access classes such that it should be simple to plugin
more efficient strategies.

# Requirements

* Apache or other webserver with php 5.5+
* apcu extension.  ( for shared mem caching. will run without, but slower. )

# Installation

On ubuntu apcu can be installed with:

   apt-get install php5-apcu
   
The website code can then be installed by:

1. git clone this repository to your docroot or somewhere beneath it.
2. cp settings.json.example settings.json
3. edit settings.json and edit the value of "data_dir" to reflect the location of
the bitsquare data files on your system.
4. Make sure that bitsquare is running with flag --dumpStatistics true

Navigate in your browser to your webserver docroot.

That's it!


# API

For now, just check out the API subdirectory.  docs are todo.