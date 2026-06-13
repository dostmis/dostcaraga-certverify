'use strict';

/**
 * CERTiFY Hedera bridge.
 *
 * A tiny, localhost-only HTTP service that holds the Hedera operator key and
 * signs Consensus Service submissions on behalf of the Laravel app. The Laravel
 * app calls this over 127.0.0.1; the key never leaves this process.
 *
 * Endpoints:
 *   GET  /health        -> { ok, network, account, topicConfigured }
 *   POST /create-topic  -> { topicId }              (one-time setup)
 *   POST /anchor        -> { sequenceNumber, consensusTimestamp, transactionId }
 */

require('dotenv').config();

const express = require('express');
const {
  Client,
  PrivateKey,
  TopicCreateTransaction,
  TopicMessageSubmitTransaction,
} = require('@hashgraph/sdk');

const PORT = parseInt(process.env.PORT || '3001', 10);
const HOST = process.env.HOST || '127.0.0.1';
const NETWORK = (process.env.HEDERA_NETWORK || 'testnet').toLowerCase();
const ACCOUNT_ID = process.env.HEDERA_ACCOUNT_ID || '';
const PRIVATE_KEY = process.env.HEDERA_PRIVATE_KEY || '';

if (!ACCOUNT_ID || !PRIVATE_KEY) {
  console.error('[hedera-bridge] FATAL: HEDERA_ACCOUNT_ID and HEDERA_PRIVATE_KEY must be set in hedera/.env');
  process.exit(1);
}

function buildClient() {
  let client;
  switch (NETWORK) {
    case 'mainnet':
      client = Client.forMainnet();
      break;
    case 'previewnet':
      client = Client.forPreviewnet();
      break;
    case 'testnet':
    default:
      client = Client.forTestnet();
      break;
  }
  // ECDSA keys are common in the portal; fall back if ED25519 parsing fails.
  let key;
  try {
    key = PrivateKey.fromStringECDSA(PRIVATE_KEY);
  } catch (e) {
    key = PrivateKey.fromString(PRIVATE_KEY);
  }
  client.setOperator(ACCOUNT_ID, key);
  return client;
}

const client = buildClient();

const app = express();
app.use(express.json({ limit: '256kb' }));

app.get('/health', (req, res) => {
  res.json({
    ok: true,
    network: NETWORK,
    account: ACCOUNT_ID,
    topicConfigured: Boolean(process.env.HEDERA_TOPIC_ID),
  });
});

app.post('/create-topic', async (req, res) => {
  try {
    const memo = (req.body && req.body.memo) ? String(req.body.memo) : 'dostcaraga-certify';
    const tx = await new TopicCreateTransaction().setTopicMemo(memo).execute(client);
    const receipt = await tx.getReceipt(client);
    const topicId = receipt.topicId.toString();
    console.log(`[hedera-bridge] created topic ${topicId} (memo="${memo}")`);
    res.json({ topicId });
  } catch (err) {
    console.error('[hedera-bridge] create-topic error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

app.post('/anchor', async (req, res) => {
  try {
    const topicId = req.body && req.body.topicId;
    const message = req.body && req.body.message;

    if (!topicId || !message) {
      return res.status(400).json({ error: 'topicId and message are required' });
    }

    const submit = await new TopicMessageSubmitTransaction()
      .setTopicId(topicId)
      .setMessage(message)
      .execute(client);

    const receipt = await submit.getReceipt(client);
    const record = await submit.getRecord(client);

    res.json({
      sequenceNumber: receipt.topicSequenceNumber.toString(),
      consensusTimestamp: record.consensusTimestamp.toString(),
      transactionId: submit.transactionId.toString(),
    });
  } catch (err) {
    console.error('[hedera-bridge] anchor error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

app.listen(PORT, HOST, () => {
  console.log(`[hedera-bridge] listening on http://${HOST}:${PORT} (network=${NETWORK}, account=${ACCOUNT_ID})`);
});
