# Установка
Добавить в composer.json
``` 
"repositories": [

        {
            "type": "git",
            "url": "https://github.com/Atwinta/composer-require-ext.git"
        }
    ],
```
выполнить команду composer require "lira/composer-ext dev-master"

# Официальная документация
# composer-require-ext

This plugin is useful for teams working on closed projects big enough to split code into packages, but not big enough to bother of full-functional repository  ([Satis](https://github.com/composer/satis) or similar).

This Composer plugin adds **"require-ext"** command which makes using of private repositories and packages smoother.

Provided **"require-ext"** command works exactly the same way normal Composer **"require"** do, but it understands VCS URLs as well as package names and automatically adds them to `repositories` section of project's `composer.json`

## Usage

Add the plugin to your project by:

```
composer require guest-one/composer-require-ext --dev
```

Now you can just add a package by URL from repository which is not indexed by Packagist/Satis:

```
composer require-ext https://bitbucket.org/GuestOne/tool-cbr.ru-currency-updater.git  
```

Normally you must [manually edit composer.json](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository) to achive the same effect.



## Special options

### --from-repo (-F)

Special option to specify exact package version and repository URL in one command:

```
composer require-ext guest-one/tool-cbr.ru-currency-updater --from-repo https://bitbucket.org/GuestOne/tool-cbr.ru-currency-updater.git  
```


### --insecure (-K)

Allows using insecure (http) URL for repositores:

```
composer require-ext -K http://gitlab.localdomain/public/my-package.git
```

This option changes `secure-http` to `false` in project's `composer.json` if you are requiring insecure URL.

## Troubleshooting

### Problem: Old Composer verson

Error message aftrer `composer require guest-one/composer-require-ext`

```
  - guest-one/composer-require-ext x.x.x requires composer-plugin-api ^2.0 -> no matching package found.
```

Update Composer to solve this issue:

```
 composer self-update
```
