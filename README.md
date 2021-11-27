This extension adds "Previous file" and "Next file" links to pages like "File:Something 123.png".
These links will point to "File:Something 122.png" and "File:Something 124.png" respectively.

If previous or next file doesn't exist, then Previous or Next link will be omitted.

Note: you can add the following in `MediaWiki:Common.css` to hide the other links (like "Metadata" or "File history"), if you don't need them:
```css
#filetoc li a[href^="#"] { display: none; }
```

== Other features ==

If an article A has {{#set_associated_image:Something.pdf}} and {{#set_associated_index:12}},
then visiting File:Something.pdf?page=12 will have "Return to text view" link that points to article A.
Note that {{#set_associated_index:1}} is necessary for one-page images (like non-PDF files).

You can override the automatically guessed "Previous file" and "Next file" links
by adding {{#set_prev_next:A.png|B.png}} to the File page,
in which case Prev link will point to File:A.png, and Next link will point to File:B.png.
Both parameters are optional, so {{#set_prev_next:|B.png}} will set only the Next link,
and {{#set_prev_next:A.png}} will set only the Prev link. 
