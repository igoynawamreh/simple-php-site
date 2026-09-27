# Simple PHP Site

Named *Simple PHP Site* because, out of the box, it only provides the basics — routing, templating, variables, and functions — just enough to build a website. But you're free to write your own code on top of it, whether that means a larger web application backed by SQLite, MySQL, PostgreSQL, or anything else built to handle bigger data.

Not much to document here. The setup is simple — just download or clone it, run the examples, and you'll get it.

Everything you'll need is inside `app`. Everything else — routing, templates, content sources — is defined in `config.php`.

`app` is just a folder name, not a rule. Rename it, copy it, run several side by side if you need to. Each one gets its own entry in `config.php`, where you tell it what URLs it handles, which templates to use, and where its content comes from.

For the exact list of what's available inside a page — variables, functions — check the source directly: `engine/variable.php`, `engine/route.php`, and `engine/function.php`.

Markdown-based content works out of the box, backed by a simple file cache — fine for a few hundred or even a few thousand pages. Planning for tens of thousands? You'll want to swap in something built for that scale, like SQLite or MySQL.

The frontend is just as flexible. Stick with plain HTML-CSS-JS, or bring in a framework like Alpine.js, Vue, React, or whatever fits your workflow.
