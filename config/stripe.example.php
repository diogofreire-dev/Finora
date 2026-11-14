<?php
/**
 * Configuração do Stripe (pagamentos do Finora Pro) — EXEMPLO
 * Copia este ficheiro para stripe.php (ignorado pelo git) e preenche.
 *
 * Começa SEMPRE pelo modo de teste (chaves sk_test_… e whsec_… do modo de teste):
 * os pagamentos são a fingir, com cartões de teste como 4242 4242 4242 4242.
 *
 * 1. Chave secreta: Stripe → Developers → API keys → Secret key (sk_test_…).
 *    Melhor ainda: uma "Restricted key" só com escrita em Checkout Sessions, Customer portal,
 *    Customers e Subscriptions.
 * 2. Webhook: Stripe → Developers → Webhooks → Add endpoint
 *      URL:     https://<dominio>/site/webhooks/stripe.php
 *      Eventos: checkout.session.completed, customer.subscription.created,
 *               customer.subscription.updated, customer.subscription.deleted
 *    Depois copia o "Signing secret" (whsec_…).
 *    No localhost usa a Stripe CLI:  stripe listen --forward-to localhost/Finora/site/webhooks/stripe.php
 * 3. Portal do cliente (para cancelar a subscrição): Settings → Billing → Customer portal → Save.
 */

define('STRIPE_SECRET_KEY',     'sk_test_COLOCA_AQUI');
define('STRIPE_WEBHOOK_SECRET', 'whsec_COLOCA_AQUI');

// Opcional: o id de um preço criado no Stripe (Product catalog → Finora Pro → 2,99 € / mês).
// Com o Managed Payments, esse produto tem de ter o código fiscal "SaaS - personal use" (txcd_10103000).
// Sem ele, cada checkout cria o preço a partir de PRO_PRICE_CENTS (site/helpers/plan.php).
// define('STRIPE_PRICE_ID', 'price_…');
