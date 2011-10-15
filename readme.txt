Name:     FeedVis 
Author:   Jason Priem
email:    jp@jasonpriem.org
License:  MIT ( http://www.opensource.org/licenses/mit-license.php )
Homepage: http://jasonpriem.org/feedvis-dev

changelog:
-----------------------------------------------------------------------------------------------------------
1.0		25 Nov, 2008
1.01 	30 Nov, 2008:
		* cache_locations.php now sets default cache location to '../backend'; 
			no need to set the loc manually unless front and back are in the same folder. 
		* cache_updater->add_new_posts() now works correctly on the first run
		* the stopwords array is now stored as a text file for easier editing; it's array-ified in update.php
1.02	* cleaned out some superfluous files from the package; noted php5.2 req



How to install this and use it for your very own:
-----------------------------------------------------------------------------------------------------------
requires: 
* PHP5.2 or greater
* write permissions for the 'cache' folder

steps:
1. unzip the folders and put them where you want.
2. IF you are separating the frontend and backend folders, update /frontend/cache_locations.php with the absolute location of the cache folder.
	If they're staying in the same folder, you can skip this.
2. Get your opml file (it's just a list of feeds; most feedreaders export them) and call it feeds.opml; drop it in the 'backend' folder
3. Finally, you'll need to set up a cron job on your server to run the feedreader every so often, unless you want to do it manually.

There are several display options you can edit; these are mostly in cal.es
You can also change the length of the stored posts cache (right now it's one month); it's a line at the beginning of cache-updater.php.

feel free to drop me a line with any questions or comments.
