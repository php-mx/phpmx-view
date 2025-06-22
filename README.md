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

Organize seus arquivos de visualização em subpastas como `_base`, `_component` e `_page` para facilitar a manutenção e reutilização.

Exemplo:

```text
/view/_base/layout.html
/view/_component/button.html
/view/_page/home.html
```

Você pode renderizar um template em seu controller assim:

```php
return View::render('_page/home', ['usuario' => $usuario]);
```

---

## Documentação

- Consulte o [README do PHPMX Core](../phpmx-core/README.md) para detalhes sobre os diretórios `helper`, `source`, `storage` e `terminal`.

- **Helper**

  - [function](./.doc/helper/function.md)

- **Source**
  - [View](./.doc/source/View.md)

---

[phpmx](https://github.com/php-mx) | [phpmx-core](https://github.com/php-mx/phpmx-core) | [phpmx-server](https://github.com/php-mx/phpmx-server) | [phpmx-datalayer](https://github.com/php-mx/phpmx-datalayer) | [phpmx-view](https://github.com/php-mx/phpmx-view)
