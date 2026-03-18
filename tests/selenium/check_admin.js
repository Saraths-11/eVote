const { Builder, By, until } = require('selenium-webdriver');

(async () => {
    let driver = await new Builder().forBrowser('chrome').build();
    try {
        // Test admin login
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys('sarath123@gmail.com');
        await driver.findElement(By.name('password')).sendKeys('2004');
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.sleep(3000);
        let url = await driver.getCurrentUrl();
        console.log('ADMIN REDIRECT URL:', url);
        console.log('Contains admin_dashboard:', url.includes('admin_dashboard'));
        console.log('Contains dashboard:', url.includes('dashboard'));
    } finally {
        await driver.quit();
    }
})();
