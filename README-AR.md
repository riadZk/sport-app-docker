# Docker setup لـ Sport App

هاد الحزمة كتوجد Development وProduction باستعمال Docker:
- Laravel + PHP-FPM
- Nginx
- MySQL 8.4
- Redis 7
- Queue Worker
- Laravel Reverb
- Vite فـ Development

## ملاحظات مهمة قبل التشغيل

1. خاص `composer.lock` و`package-lock.json` يكونو موجودين.
2. نسخ `.env.docker.example` إلى `.env`، وخلي `APP_KEY` فارغ فالأول.
3. تأكد أن `.env` ما كيتدفعش لـ Git.
4. Development MySQL عندها بيانات دخول محلية للتطوير فقط. ما تستعملهاش فـ Production.
5. Production كتشغل على port 8080 مؤقتاً للاختبار؛ ما كتبدلش منافذ 80/443 ديال أي Nginx موجود.
6. ما كايناش migrations أو database deletion أو auto-generated APP_KEY داخل entrypoint.

## Development

من PowerShell داخل project folder:
- نسخ `.env.docker.example` إلى `.env`.
- شغل الخدمات: `docker compose up -d --build`
- ولد APP_KEY مرة واحدة: `docker compose exec app php artisan key:generate`
- شغل migrations: `docker compose exec app php artisan migrate`
- افتح `http://localhost:8080`

Vite: `http://localhost:5173`
MySQL من Windows: `127.0.0.1:3307`

إلى كان عندك `.env` موجود من قبل، احتافظ بنسخة منه قبل تبديلو، وراجع القيم. Compose كيعوض DB/Redis settings داخل containers.

## Production

استعمل `.env.production` على VPS فقط، مبني على `.env.production.example`، وعمر الأسرار بقيم قوية وفريدة. ما ترفعوش لـ Git.

التشغيل المؤقت للاختبار:
`docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build`

قبل ذلك خاص:
- إعداد DNS للدومين الجديد.
- التأكد أن port 8080 متاح للاختبار.
- توليد APP_KEY مرة واحدة وإدخاله في `.env.production`.
- تعيين كلمات مرور قوية وReverb secrets.
- التأكد من وجود backup قبل أي migrations.

بعد أول تشغيل:
`docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan migrate --force`

**ما تستعملش `docker compose down -v`** إلا إذا كنت باغي تمسح بيانات MySQL وRedis نهائياً.

## ملاحظات تقنية

- Production Nginx image كيتبنى من نفس Dockerfile وكيحمل `public` وVite build من image ديال التطبيق؛ ما كاينش bind mount لـ `./public` فـ Production.
- MySQL وRedis ما عندهمش ports منشورين فـ Production، وبالتالي ما كيتعرضوش مباشرة للإنترنت.
- Queue وReverb خدامين كخدمات مستقلة.
- هادي مرحلة تجهيز وتشغيل أولي؛ GitHub Actions وSSL والدومين النهائي غادي يتربطو من بعد ما ينجح اختبار Development.
