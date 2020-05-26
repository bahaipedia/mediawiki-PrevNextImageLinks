This extension adds "Previous file" and "Next file" links to pages like "File:Something 123.png".
These links will point to "File:Something 122.png" and "File:Something 124.png" respectively.

If previous or next file doesn't exist, then Previous or Next link will be omitted.

Note: you can add the following in `MediaWiki:Common.css` to hide the other links (like "Metadata" or "File history"), if you don't need them:
```css
#filetoc li a[href^="#"] { display: none; }
```
