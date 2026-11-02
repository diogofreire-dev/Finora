# VIES API - Sistema de Lookup de Empresas por NIF

## O que é VIES?

**VIES** (VAT Information Exchange System) é o sistema oficial da União Europeia para validação de números de IVA. É:
- ✅ Grátis
- ✅ Sem autenticação
- ✅ Dados oficiais e verificados
- ✅ Funciona para Portugal (PT) e toda a UE

## Como Funciona no Finora?

### Fluxo de Lookup (get_company_by_nif.php)

```
NIF inserido no formulário
        ↓
┌─────────────────────────────┐
│ 1. Consultei BD local?      │ ← Instantâneo (<1ms)
└─────────────────────────────┘
        ↓ NÃO encontrado
┌─────────────────────────────┐
│ 2. Consultar VIES          │ ← ~600-800ms
│    (UE API oficial)         │
└─────────────────────────────┘
        ↓ Se sucesso
┌─────────────────────────────┐
│ 3. Guardar em BD local      │ ← Para próximas vezes
└─────────────────────────────┘
        ↓
  Campo preenchido
  (Utilizador confirma)
```

## Respostas Possíveis

### `"source": "local"`
- Empresa já estava na BD
- **Tempo:** <1ms
- **Custo:** Nenhum

### `"source": "vies"`
- Consultada API VIES da UE
- Empresa registada com status IVA válido
- Resultado guardado na BD local
- **Tempo:** 600-800ms (primeira vez)
- **Custo:** Nenhum

### `"source": "vies_not_found"`
- NIF existe mas não está registado no VIES
- Casos comuns: empresas isentas de IVA, não-declaradas
- Campo fica vazio → **utilizador preenche manualmente**
- **Ação:** Adicione à BD manualmente via `nif_empresas`

### `"source": "vies_error"`
- API VIES inacessível (raramente)
- Campo fica vazio → **utilizador preenche manualmente**
- Sistema continua a funcionar normalmente
- **Retry:** Próxima consulta tenta novamente

## Configuração Requerida

### Zero!

O sistema funciona imediatamente sem qualquer configuração. Basta usar.

Se VIES não responder, o campo fica vazio para entrada manual — não há quebra de funcionalidade.

## Testando

### Via terminal:

```bash
# Terminal PowerShell
$nif = "NÚMEROS9DÍGITOS"  # Ex: 500961353 (Continente)
$url = "http://localhost:8000/site/pages/get_company_by_nif.php?nif=$nif"
Invoke-WebRequest -Uri $url | Select-Object -ExpandProperty Content

# Resultado esperado (primeiro NIF - consulta VIES):
# {"found":true,"nome_empresa":"CONTINENTE HIPERMERCADOS SA","source":"vies"}

# Repetir mesma consulta (segundo NIF - consulta BD local):
# {"found":true,"nome_empresa":"CONTINENTE HIPERMERCADOS SA","source":"local"}
```

### Via browser:

```
http://localhost:8000/site/pages/get_company_by_nif.php?nif=500961353
```

## Dados de Teste (Portugal)

| NIF | Empresa | Status |
|-----|---------|--------|
| 500961353 | CONTINENTE HIPERMERCADOS SA | ✅ Válido VIES |
| 512345678 | MEO - SERVIÇOS DE COMUNICAÇÕES E MULTIMÉDIA | ✅ Válido VIES |
| 999999999 | N/A | ❌ Não registado |

## Performance

- **BD Local:** <1ms (instantâneo)
- **VIES primeira vez:** 600-800ms
- **VIES tempo limite:** 6s (se VIES não responde, sistema fallback graceful)

## Troubleshooting

| Problema | Causa | Solução |
|----------|-------|---------|
| Campo vazio após NIF | NIF não registado VIES ou VIES offline | Preencher manualmente — dados guardados para futuro |
| Timeout 6s | VIES muito lento | Retry automático próxima consulta |
| NIF rejeitado | Formato inválido (não 9 dígitos) | Verificar NIF (ex: sem espaços/traços) |

## Estrutura BD

Tabela `nif_empresas` caches dados:

```sql
CREATE TABLE IF NOT EXISTS nif_empresas (
    nif VARCHAR(9) PRIMARY KEY,
    nome_empresa VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Consultar dados em cache:

```sql
SELECT * FROM nif_empresas ORDER BY created_at DESC LIMIT 10;
```

## FAQ

**P: Preciso de uma chave de API?**
R: Não. VIES é completamente gratuito e sem autenticação.

**P: E se VIES ficar offline?**
R: Campo fica vazio. Utilizador preenche manualmente. BD local continua a funcionar normalmente.

**P: Quanto custa?**
R: Nada. VIES é serviço oficial da UE.

**P: Funciona para NIFs de outros países da UE?**
R: Sim. Adapte a URL para `ms/{PAIS}/vat/{NIF}` (ex: `ms/ES/vat/12345678Z` para Espanha).

**P: Quanto tempo até BD ficar cheia?**
R: Muito. BD cresce apenas com NIFs únicos consultados. Site com 1000 consultas/dia → 365.000 caches/ano.

---

**Última atualização:** 2026-03-26
**Estado:** Produção ✅
