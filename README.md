Extends search for content type repeat
======================

Extends the legacy storage search to get also repeater results.

### Setup

To use this search, just update the controller in your routing.yml

Example:

    search:
        path: /search
        defaults:
            _controller: controller.repeater-search:searchWithRepeater
