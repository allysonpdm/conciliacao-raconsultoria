# Object Values — Contratos e Guia de Reaproveitamento

Este documento descreve os `ObjectValues` existentes e recomendações para reaproveitamento e empacotamento na versão 2.

## Lista de ObjectValues (existentes)

- `Cnpj` — sanitização, validação (dígitos verificadores), máscara `##.###.###/####-##`, `__toString()` retorna 14 dígitos.
- `Cpf` — validação CPF.
- `CpfCnpj` — aceita CPF ou CNPJ conforme tamanho.
- `Decimal` — validação de formato, separador configurável, conversões.
- `Monetario` — alias/encapsulamento para `Decimal` com comportamento de moedas.
- `Percentual` — representa porcentagens com limites e escala.
- `Email` — validação via Symfony Validator.
- `Regex` — wrapper para validar por expressão regular.

## Contrato comum

Todos os ObjectValues devem implementar / respeitar:

- Construtor aceitando `mixed $value`.
- Método `__toString(): string` que retorna representação persistível (ex.: cnpj sem máscara).
- Métodos utilitários:
  - `sanitized(): string` — valor limpo para armazenamento.
  - `masked(): string` — valor formatado para exibição.
  - `obfuscated(): string` — versão parcial (ex.: últimos 4 dígitos) para logs/operação.
- Validação realizada em `protected function validate(): void` que lança `InvalidArgumentException` em erro.

## Padrões de projeto para reaproveitamento

1. Empacotar como pacote interno (monorepo) `packages/ra/object-values` com `composer.json` próprio.
2. Expor `Factory` e helpers estáticos:
   - `ObjectValueFactory::make('Cnpj', $value)`
   - `Cnpj::from($value)` como alias conveniência.
3. Garantir imutabilidade: VO deve ser imutável após criação.
4. Cobertura de testes: unit tests com casos válidos e inválidos, testes de máscara/format.
5. Documentar contratos em `docs/object-values.md` (este arquivo) e gerar exemplos em `README.md` do pacote.

## Exemplos de uso

```php
use App\ObjectValues\Cnpj;

$cnpj = new Cnpj('12.345.678/0001-95');
echo $cnpj->masked(); // 12.345.678/0001-95
echo (string)$cnpj;   // 12345678000195

// Factory
$cnpj2 = ObjectValueFactory::make('Cnpj', '12345678000195');
```

## Contrato de testes

- Testar entradas com espaços, caracteres especiais, zeros à esquerda.
- Testar limites (CPF inválido, CNPJ com todos dígitos iguais).
- Testar comportamento de máscara e sanitização.

## Migração para pacote

- Criar `packages/ra/object-values` com `composer.json` apontando para `App\ObjectValues`.
- Atualizar `composer.json` raiz para `autoload` apontando para o namespace do pacote (ou usar path repositories durante desenvolvimento).
- Publicar changelog e README com instruções de consumo.
