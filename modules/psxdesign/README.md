# PrestaShop Design (psxdesign)

## About
### Rework of the pages in the Design entry of the Back Office
The module aims to simplify for the merchants the customization of his store. By reworking the existing pages, we want to create a better user experience.
### New features
The module will also provide new features that are missing from PrestaShop. *(e.g.: creating a logo from a text input)*

## Download & Installation
*To do.*

## Building
This part covers the steps to get this project ready locally.

In order to run on a PrestaShop instance, dependencies needs to be downloaded and the JS application built.

### PHP
Retrieve dependencies with composer

```bash
make install-back
```

### VueJS & Scripts
To build the application in production mode:

```bash
make install-front
make build-front
```

### How to localy test the module for the first time ?
You need to run an instance of PrestaShop with the module, you can use docker-compose commands:

```bash
make setup
make install
make build-front
docker-compose start
```

* Open a new tab in your browser at `http://localhost:8000/`


To access to the backoffice:

* Open a new tab in your browser at `http://localhost:8000/admin-dev/`
* Email: `admin@prestashop.com`
* Password: `prestashop`

If you want more information about the docker image you can refere to [PrestaShop Flashlight](https://github.com/PrestaShop/prestashop-flashlight) repository.
## Commands

### Global
* `make`: Calling help by default
* `make help`: Get help on this file

### Installation and update
* `make setup`: Setup docker-compose environment
* `make build-image`: (Re)Build docker images
* `make install-back`: Install composer dependencies
* `make install-front`: Install yarn dependencies
* `make install`: Install dependencies
* `make update-back`: Update PHP dependencies
* `make update-front`: Update yarn dependencies
* `make update`: Update dependencies
* `composer-dump`: Refresh back-end routes

### Build
* `build-styles`: Build Scss prefixed styles to css
* `build-styles-watch` : Build and watch Scss prefixed styles to css
* `make build-scripts`: Build es-modules scripts
* `make build-front`: Build all front (javascript & styles)

### Deploy
* `make zip-build`: Build & make a zip bundle
* `make zip`: Make a zip bundle

### Qualimetry
* `make lint`: Launch linter
* `make lint-fix`: Launch linter and fix files
* `make lint-back`: Launch php linter
* `make lint-back-fix`: Launch php linter and fix files
* `make lint-front`: Launch JS linter
* `make lint-front-fix`: Launch JS linter and fix files
* `make run-phpstan` Launch phpstan

### Tests
* `make test`: Launch all tests
* `make test-back`: Launch the tests back
* `make test-scripts`: Launch the scripts test
* `make test-scripts-watch`: Launch the scripts test in watch mode
* `make test-front`: Launch the front test

### Others
* `make clean`: Clean up the repository
* `make dev-prepare`: Install & prepare husky & commit lint

## Releasing
### Local generation of a .zip
To generate a zip of the module locally, you can run the command:
```bash
make zip-build
```
This will:
- Build the JS
- Install vendors
- Create a `dist` folder
- Zip all the module inside the `dist` directory, with the exception of all files and folders listed in `module-files.exclude`

## Documentation
*To do.*

## Contributing
PrestaShop modules are open source extensions to the PrestaShop e-commerce platform. Everyone is welcome and even encouraged to contribute with their own improvements!

Just make sure to follow our contribution guidelines.

*To be defined*...

### Commits
To ensure that commit message are consistent, we lint commit messages by using: `commitlint`.

To contribute, please first install `commitlint` and `husky`:

```bash
make dev-prepare
```

## Reporting issues
*To do.*

## Licence
This module is released under the Academic Free License 3.0
