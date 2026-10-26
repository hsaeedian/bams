export default class Lang {

    static _langs = {

        en: {
            serverId: 'Server ID',
            automaticServerId: 'Server ID will be generated automatically when saved.',
            installTitle: "Install BAMS",
            installSuccess: "Installation completed. Sign in with your administrator account. If this resumed a previous attempt, use the password from that attempt.",
            installLocked: "Installation is locked. Existing configuration will not be changed.",
            installIntro: "Create a MariaDB database and user in cPanel Database Wizard, and grant that user privileges on the database. Enter the full names, including cPanel prefixes. No terminal or SQLite is needed.",
            installOwnership: "1. Confirm you own this hosting account",
            installKeyHelp: "Generate a setup-key file below. In cPanel File Manager, enable Show Hidden Files, create this private folder outside public_html, set its permissions to 0700, and upload setup.key into it. This prevents another visitor from installing your site first.",
            installKeyDownload: "Generate and download setup.key",
            installKeyExisting: "Already uploaded a key? Enter its contents below. Never put setup.key in public_html.",
            installKey: "Setup key",
            installDatabase: "2. Database connection",
            installHost: "Database host",
            installPort: "Database port",
            installDbName: "Database name (including cPanel prefix)",
            installDbUser: "Database username (including cPanel prefix)",
            installDbPassword: "Database password",
            installAdmin: "3. First administrator",
            installAdminUsername: "Administrator username",
            installAdminPassword: "Administrator password (12–72 bytes)",
            installConfirm: "Confirm administrator password",
            installButton: "Install BAMS",
            installPrivacy: "Passwords are never prefilled. Existing databases with data are refused. Setup locks automatically after installation. Use HTTPS before submitting passwords.",

            credential_secret: "BBB shared secret — empty preserves stored value",
            credential_login: "Adobe username — empty preserves stored value",
            adobePassword: "Adobe password — empty preserves stored value",
            newPassword: "New password (12–72 bytes); empty preserves an existing password",

            administration: "Administration",
            dashboard: "Monitoring dashboard",
            servers: "Servers",
            users: "Users",
            settings: "Settings",
            audit: "Audit log",
            title: "Display title",
            domain: "Server origin (http(s)://host:port)",
            enabled: "Enabled",
            confirmDelete: "Confirm deletion",
            delete: "Delete",
            edit: "Edit",
            add: "Add new",
            type: "Server type",
            api_path: "BBB API path",
            secret: "BBB shared secret — empty preserves stored value",
            login: "Adobe username — empty preserves stored value",
            save: "Save",
            testConnection: "Test unsaved connection",
            name: "Display name",
            role: "Role",
            adminAccess: "Administrators can access every enabled server. Editing a user invalidates their existing sessions.",
            all_servers: "Access all servers (viewers)",
            selectedServers: "Selected servers (empty means no access)",
            app_url: "Application URL (including subdirectory)",
            actor: "Actor",
            action: "Action",
            target: "Target",

            lang_dir: 'ltr',
            lang_name: 'English',
            language: 'Language',
            duration: 'Duration',
            recording: 'Recording',
            freeSpace: 'Free Space',
            participants: 'Participants',
            meetings: 'Meetings',
            moderatorCount: 'Moderators',
            participantCount: 'Participants',
            listenerCount: 'Listeners',
            voiceParticipantCount: 'Voice Participants',
            videoCount: 'Videos',
            join: 'Join',
            responseError: 'An error occurred!',
            username: 'Username',
            password: 'Password',
            userEmpty: 'Enter the username',
            passEmpty: 'Enter the password',
            loginErr: 'Username or Password is incorrect!',
            logOut: 'Log Out',
            logIn: 'Login',
            darkLight: 'Dark / Light mode',
            participantShowHide: 'Show / Hide participants',
            meetingShowHide: 'Show / Hide meetings',
            snapshot: 'Snapshot',
        },

        fa: {
            serverId: 'شناسه سرور',
            automaticServerId: 'شناسه سرور هنگام ذخیره به‌صورت خودکار ساخته می‌شود.',
            installTitle: "نصب BAMS",
            installSuccess: "نصب انجام شد. با حساب مدیر وارد شوید. اگر نصب قبلی را ادامه داده‌اید، از گذرواژه همان تلاش قبلی استفاده کنید.",
            installLocked: "نصب قفل شده است. تنظیمات موجود تغییر نخواهند کرد.",
            installIntro: "در بخش Database Wizard سی‌پنل، یک پایگاه داده و کاربر MariaDB بسازید و دسترسی کاربر به پایگاه داده را فعال کنید. نام کامل با پیشوند حساب را وارد کنید. نیازی به ترمینال یا SQLite نیست.",
            installOwnership: "۱. تأیید مالکیت حساب میزبانی",
            installKeyHelp: "فایل کلید نصب را دریافت کنید. در File Manager سی‌پنل نمایش فایل‌های مخفی را فعال کنید، پوشه خصوصی زیر را خارج از public_html با مجوز 0700 بسازید و setup.key را در آن بارگذاری کنید. این کار مانع نصب سایت توسط بازدیدکنندگان دیگر می‌شود.",
            installKeyDownload: "ساخت و دریافت setup.key",
            installKeyExisting: "اگر کلید را قبلاً بارگذاری کرده‌اید، محتوای آن را در کادر زیر وارد کنید. فایل setup.key را در public_html قرار ندهید.",
            installKey: "کلید نصب",
            installDatabase: "۲. اتصال پایگاه داده",
            installHost: "میزبان پایگاه داده",
            installPort: "درگاه پایگاه داده",
            installDbName: "نام پایگاه داده (شامل پیشوند سی‌پنل)",
            installDbUser: "نام کاربری پایگاه داده (شامل پیشوند سی‌پنل)",
            installDbPassword: "گذرواژه پایگاه داده",
            installAdmin: "۳. مدیر اولیه",
            installAdminUsername: "نام کاربری مدیر",
            installAdminPassword: "گذرواژه مدیر (۱۲ تا ۷۲ بایت)",
            installConfirm: "تکرار گذرواژه مدیر",
            installButton: "نصب BAMS",
            installPrivacy: "گذرواژه‌ها از قبل در فرم قرار نمی‌گیرند. پایگاه داده دارای اطلاعات پذیرفته نمی‌شود. نصب پس از اتمام خودکار قفل می‌شود. پیش از ارسال گذرواژه‌ها از HTTPS استفاده کنید.",

            credential_secret: "راز مشترک بیگ‌بلوباتن؛ خالی یعنی بدون تغییر",
            credential_login: "نام کاربری ادوبی؛ خالی یعنی بدون تغییر",
            adobePassword: "گذرواژه ادوبی؛ خالی یعنی بدون تغییر",
            newPassword: "گذرواژه جدید (۱۲ تا ۷۲ بایت)؛ خالی یعنی بدون تغییر",

            administration: "مدیریت",
            dashboard: "داشبورد نظارت",
            servers: "سرورها",
            users: "کاربران",
            settings: "تنظیمات",
            audit: "رویدادهای مدیریت",
            title: "عنوان نمایشی",
            domain: "آدرس سرور (http(s)://host:port)",
            enabled: "فعال",
            confirmDelete: "تأیید حذف",
            delete: "حذف",
            edit: "ویرایش",
            add: "افزودن",
            type: "نوع سرور",
            api_path: "مسیر API بیگ‌بلوباتن",
            secret: "راز مشترک؛ خالی یعنی بدون تغییر",
            login: "نام کاربری ادوبی؛ خالی یعنی بدون تغییر",
            save: "ذخیره",
            testConnection: "آزمایش اتصال بدون ذخیره",
            name: "نام نمایشی",
            role: "نقش",
            adminAccess: "مدیران به همه سرورهای فعال دسترسی دارند. ویرایش کاربر نشست‌های قبلی او را باطل می‌کند.",
            all_servers: "دسترسی به همه سرورها (بیننده)",
            selectedServers: "سرورهای انتخاب‌شده (خالی یعنی بدون دسترسی)",
            app_url: "آدرس برنامه (شامل زیرشاخه)",
            actor: "عامل",
            action: "عملیات",
            target: "هدف",

            lang_dir: 'rtl',
            lang_name: 'فارسی',
            language: 'زبان',
            duration: 'مدت زمان',
            recording: 'امکان ضبط',
            freeSpace: 'فضای خالی',
            participants: 'شرکت‌کنندگان',
            meetings: 'جلسه‌ها',
            moderatorCount: 'تعداد مجریان',
            participantCount: 'تعداد شرکت‌کنندگان',
            listenerCount: 'تعداد شنوندگان',
            voiceParticipantCount: 'تعداد گویندگان',
            videoCount: 'تعداد تصویر',
            join: 'پیوستن',
            responseError: 'خطایی رخ داده است!',
            username: 'نام کاربری',
            password: 'گذرواژه',
            userEmpty: 'نام کاربری را وارد نمایید',
            passEmpty: 'گذرواژه را وارد نمایید',
            loginErr: 'نام کاربری یا گذرواژه اشتباه است!',
            logOut: 'خروج',
            logIn: 'ورود',
            darkLight: 'حالت تیره / روشن',
            participantShowHide: 'نمایش / پنهان‌سازی شرکت‌کنندگان',
            meetingShowHide: 'نمایش / پنهان‌سازی جلسه‌ها',
            snapshot: 'Snapshot',
        },

    };

    static _values = this._langs['en'];
    static _lang = 'en';

    static set lang(x) {
        if (!(x in this._langs))
            return;

        this._values = this._langs[x];
        this._lang = x;

        if (x == localStorage.getItem('lang')) {
            document.documentElement.lang = x;
            document.documentElement.dir = this.val.lang_dir;

            let rtlBoot = document.getElementById('boot-rtl');
            let ltrBoot = document.getElementById('boot-ltr');
            if (this.val.lang_dir == 'rtl')
                ltrBoot.remove();
            else
                rtlBoot.remove();
        } else {
            localStorage.setItem('lang', x);
            location.reload();
        }
    }

    static get lang() {
        return this._lang;
    }

    static get val() {
        return this._values;
    }

}