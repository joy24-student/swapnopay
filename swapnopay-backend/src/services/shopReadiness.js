import dns from 'node:dns/promises'
import https from 'node:https'
import net from 'node:net'

// Connect only to an operator-configured address, never to an arbitrary merchant URL.
export async function checkShopDns(host, expectedAddresses, lookup = dns.lookup, baseDomain = process.env.SHOP_BASE_DOMAIN || 'shop.swapnopay.top') {
  let records
  try { records = await lookup(host, { all: true }) } catch { return false }
  const ipv4 = records.filter(r => r.family === 4 || (r.address && net.isIP(r.address) === 4))
  const pool = ipv4.length > 0 ? ipv4 : records
  if (!pool || pool.length === 0) return false

  // For platform subdomains (*.shop.swapnopay.top or shop.swapnopay.top or *.swapnopay.top),
  // DNS is platform-managed and wildcarded. If it resolved to valid IP addresses, it is ready.
  const isPlatformDomain = host === baseDomain || host.endsWith('.' + baseDomain) || host.endsWith('.swapnopay.top')
  if (isPlatformDomain) {
    const hasSpecificIps = expectedAddresses && expectedAddresses.length > 0 && !expectedAddresses.every(a => a === '127.0.0.1')
    if (hasSpecificIps) {
      return pool.every(record => expectedAddresses.includes(record.address))
    }
    return true
  }

  // For custom domains:
  if (expectedAddresses && expectedAddresses.length > 0) {
    if (expectedAddresses.length === 1 && expectedAddresses[0] === '127.0.0.1') {
      return pool.every(record => record.address === '127.0.0.1' || record.address === '::1')
    }
    return pool.every(record => expectedAddresses.includes(record.address))
  }

  return false
}

export function probeStore(host, address, merchant, reqPath = '/health.php') {
  return new Promise(resolve => {
    const request = https.get({
      hostname: host, port: 443, path: reqPath, servername: host,
      lookup: (_host, options, cb) => {
        if (address && net.isIP(address)) {
          return options?.all ? cb(null, [{ address, family: net.isIP(address) }]) : cb(null, address, net.isIP(address))
        }
        return dns.lookup(_host, options, cb)
      },
      timeout: 15000,
      rejectUnauthorized: process.env.NODE_ENV === 'production' && process.env.SHOP_ALLOW_INSECURE_TLS !== 'true',
      headers: { Accept: 'application/json', 'User-Agent': 'SwapnoPay-Launch-Check/1.0' },
    }, response => {
      let body = ''
      response.on('data', chunk => { body += chunk; if (body.length > 8192) response.destroy() })
      response.on('error', () => resolve({ ready: false, message: 'The storefront health response could not be read.' }))
      response.on('end', () => {
        try {
          const data = JSON.parse(body)
          resolve({ ready: response.statusCode === 200 && data.ready === true && data.merchant_id === merchant, message: 'The storefront is not ready yet.' })
        } catch { resolve({ ready: false, message: 'The domain is not serving the expected storefront.' }) }
      })
    })
    request.on('timeout', () => request.destroy(new Error('timeout')))
    request.on('error', () => resolve({ ready: false, message: 'Waiting for HTTPS and a valid storefront certificate.' }))
  })
}
