# SwapnoPay public website

The canonical origin is `https://swapnopay.top`. The homepage, API documentation,
public developer sandbox, privacy policy and terms are listed in `sitemap.xml`.
Each has a unique title, description, canonical link, social metadata and JSON-LD.
The structured data describes the actual pages; it includes no invented ratings,
reviews or certifications.

## Build and preview

Run from `web/`:

```sh
npm ci
npm run build
npm start
```

Commit and deploy `assets/site.css` together with the HTML, `enterprise.css`,
`workspace.js`, `robots.txt` and `sitemap.xml`. Rebuild after changing Tailwind
classes in `index.html` or `docs.html`. The generated stylesheet is checked in so
static Nginx deployments do not require Node.js at runtime.

## Publish and verify

1. Serve the site over HTTPS on `swapnopay.top`, with a valid certificate.
2. Apply the appropriate checked-in Nginx configuration and run `nginx -t`
   before reloading. The public site uses real 404 responses for missing files;
   hosted form routes retain their explicit fallback. `/docs`, `/portal` and
   `/index.html` redirect to their canonical paths. Preserve payment subdomains.
3. Check that `/`, `/docs.html`, `/portal.html`, `/robots.txt` and `/sitemap.xml`
   return 200, and an unknown path returns 404. Verify redirects do not loop.
4. Verify the domain in Google Search Console using the verification record
   issued for your account. No verification token is included in this repository.
5. Submit `https://swapnopay.top/sitemap.xml` and inspect the homepage and docs
   with Search Console's URL Inspection tool after publishing.
6. Review indexing, search queries and page experience after Google recrawls the
   site. Keep product information, pricing and API examples accurate as they change.

These changes improve crawlability and search presentation. They do not guarantee
indexing or a specific ranking. Publishing and Search Console verification are
separate from local development.

References: [Google SEO Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide),
[Sitemap submission](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
