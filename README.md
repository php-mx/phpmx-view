# PHPMX - VIEW

Módulo de visualização para renderização de páginas, componentes e templates com PHPMX.

---

## Dependência

- [phpmx-core](https://github.com/php-mx/phpmx-core)

---

## Instalação

A instalação é feita em um projeto **vazio** ou junto ao **phpmx-core**, utilizando apenas dois comandos no terminal:

```bash
composer require phpmx/view
.\vendor\bin\mx install
```

Você pode verificar se tudo está pronto executando o comando abaixo:

```bash
php mx
```

---

## Estrutura de pastas

Este pacote adiciona um item estrutural ao seu projeto:

- **view**: Diretório para templates, páginas e componentes de visualização.

### view

### Organização de Views

Organize seus arquivos de visualização dentro do diretório `view/` com nomes claros. Cada arquivo com o mesmo nome-base será automaticamente agrupado como uma única view.

Exemplo de estrutura:

/view/home.html
/view/home.css
/view/home.js

Neste exemplo, a view `home` será composta automaticamente pelos três arquivos: `home.html`, `home.css` e `home.js`.

Renderize-a no controller assim:

```php
return View::render('home'); // carrega HTML + CSS + JS
```

É possível importar partes específicas da view usando a extensão:

```php
View::render('home.css'); // carrega apenas o CSS
View::render('home.js'); // carrega apenas o JS
```

---

### Boas Práticas de Organização

Para manter seu projeto limpo e modular, use subpastas com propósito claro:

/view/base/layout.html
/view/component/button.html
/view/page/home.html

Essas pastas são apenas organizacionais. O alias será resolvido pelo caminho completo:

```php
View::render('page/home'); // carrega todos os arquivos home.* dentro de /view/page
View::render('component/button.js'); // carrega apenas o JS do botão
```

---

## Documentação

- **Helper**

  - [function](./.doc/helper/function.md)

- **Source**
  - [View](./.doc/source/View.md)

---

[phpmx](https://github.com/php-mx) | [phpmx-core](https://github.com/php-mx/phpmx-core) | [phpmx-server](https://github.com/php-mx/phpmx-server) | [phpmx-datalayer](https://github.com/php-mx/phpmx-datalayer) | [phpmx-view](https://github.com/php-mx/phpmx-view)
