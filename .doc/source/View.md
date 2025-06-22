# View

Classe abstrata responsável pelo sistema de renderização de views no ecossistema PHPMX.

```php
use PhpMx\View;
```

## Descrição Geral

A classe `View` provê métodos estáticos para renderização de arquivos de view (HTML, PHP, CSS, JS), aplicação de escopos, importação de arquivos e aplicação de "prepares" globais. Permite a composição de templates e a reutilização de componentes de interface.

## O que é uma View?

Uma _view_ pode ser:

- **Um arquivo**: Quando você chama `View::render` passando o nome do arquivo (ex: `View::render('pagina.html')`), será renderizado apenas aquele arquivo.
- **Um diretório**: Quando você chama `View::render` passando apenas o nome do diretório (ex: `View::render('blog')`), a view irá importar automaticamente os arquivos `_content`, `_style` e `_script` de dentro desse diretório, compondo a view completa.

Essa abordagem permite organizar componentes reutilizáveis e layouts complexos de forma simples e modular.

## Métodos principais

### render

Renderiza uma view e retorna seu conteúdo como string.

```php
View::render(string $ref, string|array $data = [], ...$params): string
```

- `$ref`: Referência do arquivo de view (caminho relativo).
- `$data`: Dados a serem passados para a view.
- `$params`: Parâmetros adicionais (ex: `scope`).

### renderString

Renderiza uma string aplicando os prepares globais.

```php
View::renderString(string $viewContent, string|array $data = []): string
```

- `$viewContent`: Conteúdo da view em string.
- `$data`: Dados para substituição.

### globalPrepare

Define um prepare global disponível em todas as views.

```php
View::globalPrepare($tag, $action): void
```

- `$tag`: Nome do prepare.
- `$action`: Função ou valor a ser aplicado.

## Exemplo de uso

### Exemplo de uso em PHP

```php
View::globalPrepare('nome', 'Andre');
$html = View::render('teste', ['titulo' => 'Olá mundo']);
```

### Exemplo de uma view HTML

Arquivo: `view/teste/_content.html`

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
[#VIEW:banner.html]
```

- **Chamar uma subview com caminho relativo:**

```html
[#VIEW:./banner]
```

A subview pode ser qualquer arquivo de view (HTML, PHP, etc.) e será renderizada no local onde a tag `[#VIEW:...]` for utilizada. O caminho pode ser absoluto (em relação à pasta de views) ou relativo ao diretório da view atual.

Assim, é possível compor páginas reutilizando componentes e mantendo a organização do projeto.
