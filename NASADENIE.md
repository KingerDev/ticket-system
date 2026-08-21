# Nasadenie

Aplikácia beží v Dockeri (nginx + php-fpm + queue worker) a konfiguruje sa
výhradne premennými prostredia kontajnera. Do obrazu sa žiadny `.env` nekopíruje.

## Odosielanie e-mailov (Resend)

Potvrdenia o registrácii chodia cez [Resend](https://resend.com) – HTTPS API,
takže netreba vlastný SMTP server ani otvorený port 25. Laravel 12 má transport
zabudovaný, stačí balík `resend/resend-php`, ktorý už je v `composer.json`.

Odosiela sa z domény **kinger.dev**, ktorej DNS je na Cloudflare.

### 1. Účet a doména v Resende

1. Založ účet na resend.com.
2. *Domains → Add Domain* → zadaj `kinger.dev`.
3. Resend vypíše niekoľko **TXT záznamov** (SPF a DKIM), prípadne MX pre
   `send.kinger.dev`.

### 2. DNS na Cloudflare

Pridaj presne tie záznamy, ktoré Resend vypísal. Pri TXT záznamoch sa proxy
nenastavuje, takže netreba nič prepínať; pri prípadnom MX zázname musí byť
**DNS only** (sivý obláčik), nie proxied.

Overenie zvykne trvať pár minút. Kým doména nie je overená, Resend pošle poštu
len na tvoju vlastnú adresu – na ostrú prevádzku to nestačí.

### 3. API kľúč

*API Keys → Create* s právom **Sending access**. Kľúč sa zobrazí jediný raz.

### 4. Premenné v Coolify

```
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=beanie@kinger.dev
MAIL_FROM_NAME=Beánie EF UMB 2026
MAIL_REPLY_TO_ADDRESS=<skutočná schránka organizátorov>
MAIL_REPLY_TO_NAME=Organizátori Beánie EF UMB
```

`MAIL_REPLY_TO_ADDRESS` nevynechávaj. Resend poštu **neprijíma**, takže odpoveď
hosťa na `beanie@kinger.dev` by nikam nedošla.

### 5. Skúška

```
php artisan tinker --execute='\Mail::raw("skuska", fn($m) => $m->to("tvoj@email.sk")->subject("Test"));'
```

### Lokálny vývoj

`MAIL_MAILER=log` – e-maily sa nikam neodosielajú, zapíšu sa do
`storage/logs/laravel.log`. Kľúč netreba.

### Keď sa e-mail nedoručí

Odosielanie beží vo fronte (`QUEUE_CONNECTION=database`), takže výpadok Resendu
nezhodí registráciu – hosť dostane potvrdenie o rezervácii aj tak a úloha skončí
v tabuľke `failed_jobs`. Znovu ju pošleš cez:

```
php artisan queue:retry all
```

Denný limit free tarify je 100 e-mailov (3 000 mesačne). Appka posiela **jeden
e-mail na registráciu, nie na hosťa**, takže skupina piatich ľudí = jeden e-mail.

## Ostatné premenné

```
APP_KEY=base64:...          # php artisan key:generate --show
APP_URL=https://<doména>
APP_LOCALE=sk
APP_FALLBACK_LOCALE=en
DB_CONNECTION=mysql
DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
QUEUE_CONNECTION=database
ADMIN_EMAIL=...             # prvý účet, zakladá ho `php artisan db:seed`
ADMIN_PASSWORD=...          # prázdne = seeder vygeneruje a raz vypíše
```

Ak heslo obsahuje `$`, zapni v Coolify pri premennej **Is Literal?**, inak sa
znak vyhodnotí ako začiatok premennej. Premenné nechaj ako runtime, nie build.

## Obraz a deploy

Obraz stavia GitHub Actions (`.github/workflows/build-image.yml`) pri každom
pushi do `main` a pushuje ho do GHCR ako `:latest` a `:sha`. Server obraz len
sťahuje – nebuilduje. Build na serveri (`npm run build` si vypýta ~1,5 GB RAM)
predtým zhodil celý VPS aj s ostatnými projektmi.

V Coolify preto nastav zdroj na hotový obraz:

```
ghcr.io/kingerdev/ticket-system:latest
```

Balík v GHCR je defaultne súkromný – buď ho prepni na verejný, alebo pridaj
prihlasovacie údaje do registra v Coolify.

Migrácie spúšťa entrypoint sám pri každom štarte (`php artisan migrate --force`).
Seeder nie – ten spusti ručne, keď zakladáš prvý účet.
