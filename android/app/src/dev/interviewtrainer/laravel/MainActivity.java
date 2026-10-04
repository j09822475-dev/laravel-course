package dev.interviewtrainer.laravel;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.net.Uri;
import android.os.Bundle;
import android.text.InputType;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

/**
 * Оболочка над веб-тренажёром.
 *
 * Приложение не дублирует логику: тренировка, прогресс и аккаунт живут на
 * сервере, а здесь — WebView с сохранением сессии, обработкой офлайна и
 * экраном для адреса сервера (у каждого он свой).
 */
public class MainActivity extends Activity {

    private static final String PREFS = "trainer";
    private static final String KEY_URL = "server_url";
    private static final int INDIGO = Color.rgb(79, 70, 229);

    private WebView webView;
    private String serverUrl;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);

        serverUrl = prefs().getString(KEY_URL, "");

        if (serverUrl.isEmpty()) {
            showSetup(null);
        } else {
            showWeb(serverUrl);
        }
    }

    private SharedPreferences prefs() {
        return getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    /** Экран ввода адреса: приложение бесполезно без сервера, на котором крутится тренажёр. */
    private void showSetup(String error) {
        webView = null;

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setGravity(Gravity.CENTER_VERTICAL);
        root.setBackgroundColor(Color.rgb(248, 250, 252));
        int pad = dp(24);
        root.setPadding(pad, pad, pad, pad);

        TextView title = new TextView(this);
        title.setText("Адрес тренажёра");
        title.setTextSize(TypedValue.COMPLEX_UNIT_SP, 22);
        title.setTextColor(Color.rgb(15, 23, 42));
        root.addView(title);

        TextView hint = new TextView(this);
        hint.setText("Укажите адрес, на котором развёрнуто приложение. "
                + "Прогресс и аккаунт хранятся на сервере, поэтому тренировка продолжается "
                + "с телефона и с компьютера с одного места.");
        hint.setTextSize(TypedValue.COMPLEX_UNIT_SP, 14);
        hint.setTextColor(Color.rgb(100, 116, 139));
        hint.setPadding(0, dp(8), 0, dp(16));
        root.addView(hint);

        if (error != null) {
            TextView problem = new TextView(this);
            problem.setText(error);
            problem.setTextSize(TypedValue.COMPLEX_UNIT_SP, 14);
            problem.setTextColor(Color.rgb(190, 18, 60));
            problem.setPadding(0, 0, 0, dp(12));
            root.addView(problem);
        }

        final EditText input = new EditText(this);
        input.setHint("https://trainer.example.com");
        input.setSingleLine(true);
        input.setInputType(InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(prefs().getString(KEY_URL, ""));
        root.addView(input, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT));

        Button save = new Button(this);
        save.setText("Открыть тренажёр");
        save.setAllCaps(false);
        save.setTextColor(Color.WHITE);
        save.setBackgroundColor(INDIGO);
        LinearLayout.LayoutParams buttonParams = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        buttonParams.topMargin = dp(16);
        root.addView(save, buttonParams);

        save.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                String url = normalize(input.getText().toString());

                if (url == null) {
                    Toast.makeText(MainActivity.this, "Адрес должен начинаться с http:// или https://",
                            Toast.LENGTH_LONG).show();
                    return;
                }

                prefs().edit().putString(KEY_URL, url).apply();
                serverUrl = url;
                showWeb(url);
            }
        });

        setContentView(root);
    }

    /** Приводит ввод к адресу: без схемы WebView молча не откроет страницу. */
    private String normalize(String raw) {
        String url = raw == null ? "" : raw.trim();

        if (url.isEmpty()) {
            return null;
        }

        if (!url.startsWith("http://") && !url.startsWith("https://")) {
            url = "https://" + url;
        }

        Uri parsed = Uri.parse(url);

        return parsed.getHost() == null || parsed.getHost().isEmpty() ? null : url;
    }

    private void showWeb(String url) {
        webView = new WebView(this);

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        // Сессия и локальные настройки интерфейса должны переживать перезапуск.
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);

        CookieManager.getInstance().setAcceptCookie(true);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String target) {
                Uri uri = Uri.parse(target);
                String host = Uri.parse(serverUrl).getHost();

                // Ссылки на сторонние сайты уходят в браузер, а не ломают сессию в приложении.
                if (host != null && host.equalsIgnoreCase(uri.getHost())) {
                    return false;
                }

                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, uri));
                } catch (Exception ignored) {
                    return false;
                }

                return true;
            }

            @Override
            public void onReceivedError(WebView view, int code, String description, String failingUrl) {
                showError(description);
            }
        });

        setContentView(webView);

        if (!isOnline()) {
            showError("Нет подключения к интернету");
            return;
        }

        webView.loadUrl(url);
    }

    /** Экран ошибки: отдельно повтор и отдельно смена адреса — чаще всего ошибаются именно в нём. */
    private void showError(String description) {
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setGravity(Gravity.CENTER);
        root.setBackgroundColor(Color.rgb(248, 250, 252));
        int pad = dp(24);
        root.setPadding(pad, pad, pad, pad);

        TextView title = new TextView(this);
        title.setText("Не удалось открыть тренажёр");
        title.setTextSize(TypedValue.COMPLEX_UNIT_SP, 20);
        title.setTextColor(Color.rgb(15, 23, 42));
        title.setGravity(Gravity.CENTER);
        root.addView(title);

        TextView details = new TextView(this);
        details.setText(serverUrl + "\n" + (description == null ? "" : description));
        details.setTextSize(TypedValue.COMPLEX_UNIT_SP, 13);
        details.setTextColor(Color.rgb(100, 116, 139));
        details.setGravity(Gravity.CENTER);
        details.setPadding(0, dp(8), 0, dp(20));
        root.addView(details);

        Button retry = new Button(this);
        retry.setText("Повторить");
        retry.setAllCaps(false);
        retry.setTextColor(Color.WHITE);
        retry.setBackgroundColor(INDIGO);
        retry.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                showWeb(serverUrl);
            }
        });
        root.addView(retry, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT));

        Button change = new Button(this);
        change.setText("Изменить адрес");
        change.setAllCaps(false);
        change.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                showSetup(null);
            }
        });
        LinearLayout.LayoutParams changeParams = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        changeParams.topMargin = dp(8);
        root.addView(change, changeParams);

        setContentView(root);
        webView = null;
    }

    private boolean isOnline() {
        ConnectivityManager manager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);

        if (manager == null) {
            return true;
        }

        NetworkInfo info = manager.getActiveNetworkInfo();

        return info != null && info.isConnected();
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
            return;
        }

        // С первой страницы кнопка «назад» — единственный доступ к настройкам адреса.
        new AlertDialog.Builder(this)
                .setTitle("Закрыть тренажёр?")
                .setPositiveButton("Выйти", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        finish();
                    }
                })
                .setNeutralButton("Изменить адрес", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        showSetup(null);
                    }
                })
                .setNegativeButton("Отмена", null)
                .show();
    }

    private int dp(int value) {
        return (int) TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, value,
                getResources().getDisplayMetrics());
    }
}
