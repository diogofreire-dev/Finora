# 🔑 Como Configurar NIF.PT API (Opcional)

## Porquê nif.pt?
- **Gratuito**: Até 100 pedidos/dia
- **Português**: Devolve nomes de empresas em PT
- **Completo**: Nome, actividade, morada

## Passos:

### 1. Registar Conta
1. Vai a https://www.nif.pt/api
2. Cria conta gratuita
3. Recebes uma chave API por email

### 2. Configurar no Finora
1. Abre `config/db.php`
2. Adiciona esta linha no topo (depois dos comentários):

```php
define('NIFPT_KEY', 'a-tua-chave-aqui');
```

### 3. Testar
1. Abre `site/pages/invoices.php`
2. Digita um NIF (ex: 503789372)
3. Deve aparecer "Encontrado via nif.pt"

## Sem Chave:
- Sistema usa apenas cache local + VIES
- VIES valida NIF mas **não devolve nome** em Portugal (privacidade)

## Exemplo de Resposta nif.pt:
```json
{
  "result": "success",
  "records": {
    "503789372": {
      "title": "STAPLES LDA",
      "activity": "Comércio a retalho de artigos de papelaria",
      "address": "Rua exemplo, 1234-567 Lisboa"
    }
  }
}
```