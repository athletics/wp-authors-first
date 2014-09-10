# WP Authors First

Changes WordPress’ default permalink structure, moving authors to the site root. This enables the following:

**Author Page:**

`.com/{author-name}/`

**Post Permalink:**

`.com/{author-name}/{year}/{month}/{slug}/`

However, WordPress very much wants to place author pages behind some kind of flag (i.e. `.com/author/{author-name}/`). Overriding this behavior involves more dramatic changes to the [WP->parse_request()](http://codex.wordpress.org/Query_Overview#More_on_WP-.3Eparse_request.28.29) process.

WP Authors First solves this problem, and handles a range of concerns related to making a dramatic permalink change of this kind:

* We expect to change an existing permalink structure; meaning, we provide 301 redirection from the previous permalinks to the new. This applies to posts and author pages.
* Caching is sprinkled throughout to minimize database calls.
* Works with the popular [Co-Authors Plus](https://wordpress.org/plugins/co-authors-plus/) plugin.
* Was originally built for a site running on [WordPress VIP](http://vip.wordpress.com/), the same grid that powers [wordpress.com](http://wordpress.com), and was deployed on a site with ~100m monthly pageviews.

## What’s Included

* This plugin is a work in progress. It was written for a specific permalink structure and may need modifications before use.
* WP Authors First currently depends on Co-Authors Plus.
* A new rewrite tag `%__coauthor%` is created for use in permalink structure. The rewrite tag is replaced with a guest author `user_nicename`.
* For performance, the use of an object cache is encouraged.
* WP Authors First may be installed using [Composer](https://getcomposer.org/).

## Credits

WP Authors First is an [Athletics](http://athleticsnyc.com) and [Thought & Expression](http://thought.is) collaboration. The code was originally developed to support the permalink structure found on [Thought Catalog](http://thoughtcatalog.com/). Many thanks to the [WordPress VIP](http://vip.wordpress.com) team for assistance in developing, reviewing and deploying this project.