# PHPMX - VIEW

Módulo de visualização para renderização de páginas, componentes e templates com PHPMX.

```bash
composer require phpmx/view
.\vendor\bin\mx install
```

---

# View

Classe abstrata responsável pelo sistema de renderização de views no ecossistema PHPMX.

```php
use PhpMx\View;
```

## Descrição Geral

A classe `View` provê métodos estáticos para renderização de arquivos de view (HTML, PHP, CSS, JS), aplicação de escopos, importação de arquivos e aplicação de "prepares" globais. Permite a composição de templates e a reutilização de componentes de interface.

## O que é uma View?

Uma **view** pode ser:

- **Um grupo**: Quando você chama `View::render` passando o nome de uma view (ex: `View::render('pagina')`), todos os arquivos com esse nome (ex: `pagina.html`, `pagina.css`, `pagina.js`, `pagina.php`) serão importados automaticamente.

- **Um arquivo**: Quando você chama `View::render` com extensão (ex: `View::render('pagina.css')`), será importado apenas aquele arquivo específico, sem encapsulamento.

## Métodos principais

### render

Renderiza uma view e retorna seu conteúdo como string.

```php
View::render(string $ref, string|array $data = [], ...$params): string
```

- `$ref`: Referência da view (sem extensão ou com extensão se quiser isolar um arquivo).
- `$data`: Dados a serem passados para a view.
- `$params`: Parâmetros adicionais (ex: `scope`).

### renderString

Renderiza uma string aplicando os prepares globais.

```php
View::renderString(string $viewContent, string|array $data = []): string
```

- `$viewContent`: Conteúdo da view em string.
- `$data`: Dados para substituição.

### prepare

Define um prepare global disponível em todas as views.

```php
View::prepare($tag, $action): void
```

- `$tag`: Nome do prepare.
- `$action`: Função ou valor a ser aplicado.

### mediaStyle

Define media queries dinamicas para folhas de estilo.

```php
View::mediaStyle($media, $queries): void
```

- `$media`: Nome da media persinalizada.
- `$queries`: media querie CSS.

## Exemplo de uso

### Exemplo de uso em PHP

```php
View::prepare('nome', 'André'); // [#name] acessada globalmente
$html = View::render('teste', ['title' => 'Olá mundo']); // [#title] acessada apenas na view teste
```

### Exemplo de uma view HTML

Arquivo: `view/teste.html`

```html
<h1>[#titulo]</h1>
<p>Bem-vindo, [#nome]!</p>
```

No exemplo acima, as tags `[#titulo]` e `[#nome]` serão substituídas pelos valores passados via dados e prepares globais.

### Subview

Você pode utilizar a tag prepare `[#VIEW]` para incluir subviews dentro de uma view principal. Essa tag é processada automaticamente pelo sistema de prepares globais.

#### Exemplos de uso:

- **Chamar uma subview diretamente:**

```html
[#VIEW:banner]
```

- **Chamar uma subview com caminho relativo:**

```html
[#VIEW:./banner]
```

A subview pode ser qualquer arquivo de view (HTML, PHP, etc.) e será renderizada no local onde a tag `[#VIEW:...]` for utilizada. O caminho pode ser absoluto (em relação à pasta de views) ou relativo ao diretório da view atual.

Assim, é possível compor páginas reutilizando componentes e mantendo a organização do projeto.

### View JS

Views JS contêm scripts JavaScript:

```js
// home.js
alert("ola mundo");
```

Quando são carregadas dentro de um conjunto de views, elas são automaticamente encapsuladas em uma tag `<script>`:

```php
// Arquivos:
- home.html
- home.js

View::render('home'); // gera: <script>...home.js...</script>
```

Se você chamar diretamente `View::render('home.js')`, o conteúdo não será encapsulado, permitindo uso livre.

---

### View CSS

Views CSS contêm estilos e são encapsuladas automaticamente em uma tag `<style>` quando fazem parte de um grupo:

```php
- home.html
- home.css

View::render('home'); // gera: <style>...home.css...</style>
```

#### Media Styles

Você pode utilizar **media styles** personalizados em seu CSS, utilizando aliases definidos no PHP.

Para definir um alias de mídia globalmente, use:

```php
View::mediaStyle('minhamedia', 'screen and (min-width: 900px)');
```

No CSS, utilize normalmente:

```css
@media minhamedia {
  body {
    background: red;
  }
}
```

Durante o processo de renderização, o alias será substituído pela mídia real.

#### Media styles padrão incluídos:

```php
View::mediaStyle('tablet',  'screen and (min-width: 700px)');
View::mediaStyle('desktop', 'screen and (min-width: 1200px)');
View::mediaStyle('print',   'print');
```

Esses aliases estão disponíveis por padrão e podem ser sobrescritos ou expandidos conforme necessário.

---

### View PHP

Views PHP são renderizadas como view HTML e importadas **antes** das demais views do grupo:

````php
- home.html
- home.css
- home.js
- home.php

View::render('home'); // Ordem: home.php, home.html, home.css, home.js

---

#### Acesso a dados

Os dados passados como segundo argumento para `View::render()` ficam disponíveis diretamente como variáveis dentro da view:

```php
// Controller
return View::render('home', ['usuario' => $usuario]);
````

```php
<!-- home.php -->
<p>Olá, <?= $usuario['name'] ?>!</p>
```

Todas as chaves do array serão convertidas para variáveis no escopo local da view PHP.

---

#### Alteração de dados

Se precisar alterar valores para subviews subsequentes, utilize a variável `$__DATA`:

```php
$__DATA['name'] = 'novo nome';
```

---

#### Uso de `__scope`

A variável especial `__scope` será substituída automaticamente por um hash único, gerado a partir do nome da view atual.

Você pode usá-la para:

- Scopar seletores CSS (`.__scope {}`)
- Gerar `id`s únicos em HTML (`id="__scope"`)
- Evitar colisões entre múltiplas instâncias da mesma view em tela

---

[phpmx](https://github.com/php-mx) | [phpmx-core](https://github.com/php-mx/phpmx-core) | [phpmx-server](https://github.com/php-mx/phpmx-server) | [phpmx-datalayer](https://github.com/php-mx/phpmx-datalayer) | [phpmx-view](https://github.com/php-mx/phpmx-view)
