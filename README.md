# micro-CMS (uCMS)

**This readme is still incomplete. Hopefuly, I will fix it soon.**

A PHP framework for creating simple web pages (e.g., info pages for lectures). Whilst minimalistic, this framework aims to uphold the best programming practices, namely:

* KISS (Keep It Stupid Simple) -- the deployment and content management must be simple enough, so that user with HTML/Markdown knowledge has to be able to use it directly.
* DRY (Don't Repeat Yourself) -- the framework automatically handles the page layout template (header, menu, ...), so the user should write only the contents.
* SOLID -- the internal interfaces are designed so that framework can be easily extended and configured.

Bulky CMS frameworks also provide clever data management for the contents (DB storage, versioning, etc.) and UI for editting the contents. The uCMS does not. It is user's job to do this, so the web page may be easily deployed almost anywhere (only PHP is requried) and stored anywhere (e.g., on GitHub). The user may manage the contents in his/her favorite text editor.


## Deployment

The Git holds not only the framework itself, but it is a working demo of its usage. So you might just take it and tweak it right away. You need only to:
* deploy it on a web server with PHP 7.3+,
* run `composer install` to set up dependencies (you need to install Composer if you do not have it)


## Philosophy

The idea is, that the user shall design static content almost as if designing a static web. The uCMS provides preprocessing only where needed. For instance, the user may write contents in Markdown and the framework ensures it is properly translated to HTML and embeded into default layout template.

### Application structure

The whole application has `index.php` bootstrap script. It is a wise precaution to direct all requests there (see attached `.httaccess`). The remaining directories have the following meaning:

* `app` -- the actual code of uCMS
* `config` -- main configuration (currently only in the `config.yaml` file)
* `vendor` -- directory where composer installs dependencies

Remaining directories hold the contents and can be modified in configuration. The configuration is always set for a top-level directory and it is applied for all its subdirectories and files recursively.

* `templates` -- Latte templates for top-level layout of the pages (default content page and error pages).
* `pages` -- The actual content that is being preprocessed.
* `resources` -- Additional resources that should not be preprocessed (e.g., global CSS files, images, ...). It might be possible to configure the web server to bypass `index.php` bootstrap and serve this content directly (depending on your actual needs).
* `downloads` -- Data offered for download (slightly different handling than resources).

Finally, the application requires directory for temporary data (cache). By default, the directory is `tmp` in application root and it should be made writeable by the web server.

### Preprocessing

TODO 

At present, the framework supports
* HTML (`*.html`, `*.htm`)
* Markdown (`*.md`)
files for displaying contents.


### Translations

TODO


## Confiuration

TODO

## Extending Framework

You may extend the code by writing your own *processors* -- classes that can load/transform the contents (e.g., `Markdown` processor transforms Markdown in HTML, so it can be embedded). Actually, if you write a cool preprocessor, consider contributing to this project (PRs are welcome).

TODO

## Roadmap



---

[Flag icons](https://www.iconfinder.com/iconsets/flags_gosquared)
