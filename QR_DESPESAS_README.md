# Funcionalidade QR Code para Despesas - FreeCard

## Visão Geral

Esta funcionalidade permite registar despesas automaticamente através da leitura de QR Codes de faturas portuguesas (formato AT - Autoridade Tributária).

## Funcionalidades

### ✅ Leitura de QR Code
- **Câmara em tempo real**: Aponte a câmara do telemóvel para o QR Code
- **Upload de imagem**: Carregue uma foto da fatura se a câmara não funcionar
- **Processamento inteligente**: Múltiplas tentativas com diferentes algoritmos (ZXing + jsQR)

### ✅ Extração Automática de Dados
- **Valor total**: Extraído automaticamente do campo "O" do QR
- **Data**: Convertida do formato YYYYMMDD para DD/MM/YYYY
- **NIF do emitente**: Campo "A" do QR Code
- **Número da fatura**: Campo "G" (FT, FS, FR)

### ✅ Lookup Inteligente de Empresas
- **Cache local**: Empresas já consultadas são guardadas para resposta instantânea
- **API VIES**: Consulta a base de dados oficial da UE para empresas portuguesas
- **Aprendizado**: Sistema aprende com as suas escolhas para automatizar futuras importações

### ✅ Auto-detecção de Categorias
- **Baseado em NIF**: Empresas com o mesmo NIF mantêm a mesma categoria
- **Aprendizado contínuo**: Primeira vez seleciona manualmente, próximas vezes automático
- **Exemplos**:
  - McDonald's → Alimentação
  - Galp → Combustível
  - Worten → Casa/Eletrodomésticos

## Arquivos Criados

### Páginas
- `site/pages/3_index.php` - Página principal para registar despesas (com QR)
- `site/pages/4_despesas.php` - Página para visualizar histórico de despesas

### API Backend
- `site/pages/processar_qr_despesa.php` - Processamento de QR Codes e criação de despesas

### Base de Dados
- `database/tables/qr_despesas_tables.sql` - SQL para criar tabelas necessárias
- `nif_empresas_cache` - Cache de NIF → Nome da empresa
- `qr_despesas_import` - Registo de importações via QR
- `categorias_auto` - Aprendizado de categorias por NIF

## Como Usar

### 1. Aceder às Páginas
```
http://localhost:8000/site/pages/3_index.php  # Registar despesa
http://localhost:8000/site/pages/4_despesas.php  # Ver despesas
```

### 2. Registar Despesa via QR
1. Clique no botão **"QR Code"**
2. Escolha entre:
   - **Iniciar Câmara**: Para scan em tempo real
   - **Carregar Imagem**: Para foto de fatura
3. Aponte para o QR Code da fatura
4. Confirme os dados extraídos
5. Clique **"Registar Despesa"**

### 3. Funcionamento do Sistema

#### Primeira Vez com uma Empresa
```
QR lido → Sistema encontra NIF → Consulta VIES API → Mostra nome da empresa
                                      ↓
Usuário seleciona categoria → Sistema guarda para futuras importações
```

#### Próximas Vezes
```
QR lido → Sistema reconhece NIF → Nome + categoria preenchidos automaticamente
```

## Formato QR Suportado

O sistema suporta QR Codes no **formato AT Portugal**:

```
A:123456789*B:987654321*F:20231225*G:FT 2023/001*O:25.50
│  │         │  │         │  │          │  │         │
│  └─ NIF    │  └─ NIF    │  └─ Data    │  └─ Nro   │
│     emit.     adquir.      YYYYMMDD      fatura    └─ Total
```

### Campos Extraídos
- **A**: NIF do emitente (empresa)
- **F**: Data (YYYYMMDD)
- **G**: Número do documento
- **O**: Total a pagar

## Tecnologias Utilizadas

### Frontend
- **HTML5-QRCode**: Leitura de QR via câmara
- **ZXing**: Decoder robusto para imagens
- **jsQR**: Fallback para processamento de imagens
- **Bootstrap 5**: Interface responsiva

### Backend
- **PHP 8.x**: Processamento server-side
- **PDO/MySQL**: Base de dados
- **cURL**: Consultas às APIs externas

### APIs Externas
- **VIES API**: Base de dados oficial da UE para validação de IVA
- **Cache local**: Para evitar chamadas repetidas

## Resolução de Problemas

### QR Code não é reconhecido
1. **Imagem de baixa qualidade**: Tire uma foto melhor, com boa iluminação
2. **QR danificado**: Verifique se o QR não está rasgado ou sujo
3. **Formato não suportado**: Apenas QR Codes de faturas portuguesas (formato AT)

### Nome da empresa não aparece
1. **Primeira vez**: Sistema consulta API VIES (pode demorar 1-2 segundos)
2. **Empresa não registada**: Algumas empresas podem não estar na base VIES
3. **Cache vazio**: Sistema guarda automaticamente para próximas vezes

### Categoria não é sugerida
1. **Primeira vez**: Selecione manualmente, sistema aprende
2. **NIF diferente**: Cada NIF tem a sua própria categoria aprendida

## Segurança

- ✅ Dados QR são processados localmente no browser
- ✅ Apenas dados essenciais são enviados ao servidor
- ✅ Consultas a APIs externas são seguras (HTTPS)
- ✅ Cache local evita exposição desnecessária

## Performance

- **Cache inteligente**: Empresas consultadas uma vez ficam guardadas
- **Processamento assíncrono**: Interface não fica bloqueada durante processamento
- **Redimensionamento automático**: Imagens grandes são otimizadas antes do processamento

## Próximas Melhorias

- [ ] Suporte a mais formatos de QR (não só Portugal)
- [ ] Reconhecimento OCR para faturas sem QR
- [ ] Integração com apps de banco para importação automática
- [ ] Relatórios avançados de gastos por empresa

---

**Estado**: ✅ Funcional e testado
**Última atualização**: 2026-03-26