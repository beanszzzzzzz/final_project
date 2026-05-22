// Example Mercure subscriber (browser)
// Replace MERCURE_URL with your hub URL, and optionally include ?topic=/customers or specific topics

const MERCURE_URL = (window.MERCURE_URL ?? '') || 'https://localhost/.well-known/mercure?topic=/customers';

function startSubscriber(onMessage) {
  const es = new EventSource(MERCURE_URL);

  es.onmessage = (e) => {
    try {
      const payload = JSON.parse(e.data);
      onMessage(payload);
    } catch (err) {
      console.error('Invalid message payload', err);
    }
  };

  es.onerror = (err) => {
    console.error('Mercure connection error', err);
    // reconnect strategy could be added here
  };

  return es;
}

// Usage:
// const es = startSubscriber((payload) => console.log('Customer update', payload));
// To stop: es.close();
