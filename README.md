# Simple PHP Site

Download or clone this repository to get started.

Everything you need to touch lives inside `app`. Everything else — routing, templates, content sources — is defined in `config.php`.

`app` is just a folder name, not a rule. Rename it, copy it, run several side by side if you need to. Each one gets its own entry in `config.php`, where you tell it what URLs it handles, which templates to use, and where its content comes from.

I've kept the docs short on purpose. The setup is simple enough that the examples included will explain it faster than I can in writing.

If you want to know exactly what's available to you inside a page — which variables, which functions — go straight to the source: `engine/variable.php`, `engine/route.php`, and `engine/function.php`.

Markdown-based content works out of the box, but that's just the default, not a requirement. It's backed by a simple file cache, so it's fine for a few hundred or even a few thousand pages — but if you're planning tens of thousands, you'll want to swap in something built for that scale, like SQLite or MySQL.

Same goes for the frontend. You can also use a JS framework like Alpine.js, Vue, React, or something else if you want.
