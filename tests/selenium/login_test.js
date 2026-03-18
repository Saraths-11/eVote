const { Builder, By, Key, until } = require('selenium-webdriver');
const chrome = require('selenium-webdriver/chrome');

async function runLoginTest() {
    // Set up Chrome options (e.g., headless if running in CI)
    let options = new chrome.Options();
    // options.addArguments('--headless'); 

    let driver = await new Builder()
        .forBrowser('chrome')
        .setChromeOptions(options)
        .build();

    try {
        console.log("Starting Login Test...");
        
        // 1. Navigate to Login Page
        await driver.get('http://localhost/evote/login.php');
        await driver.wait(until.titleIs('Login - eVote'), 5000);
        console.log("Login page loaded.");

        // 2. Perform Login
        await driver.findElement(By.name('email')).sendKeys('sarath123@gmail.com');
        await driver.findElement(By.name('password')).sendKeys('2004', Key.RETURN);
        
        // 3. Wait for Dashboard to load
        await driver.wait(until.urlContains('admin_dashboard.php'), 5000);
        console.log("Successfully logged in to Admin Dashboard.");

        // 4. Surf through sections
        const sections = [
            { name: 'Manage Elections', url: 'manage_elections.php' },
            { name: 'Participants', url: 'view_participants.php' },
            { name: 'Election Results', url: 'view_results.php' },
            { name: 'Audit Logs', url: 'view_logs.php' }
        ];

        for (let section of sections) {
            console.log(`Navigating to ${section.name}...`);
            await driver.get(`http://localhost/evote/${section.url}`);
            await driver.wait(until.urlContains(section.url), 5000);
            console.log(`${section.name} page loaded successfully.`);
            await driver.sleep(1000); // Small pause for visibility
        }

        // 5. Logout
        console.log("Logging out...");
        await driver.get('http://localhost/evote/logout.php');
        await driver.wait(until.urlContains('login.php'), 5000);
        console.log("Logout successful. Test Passed.");

    } catch (error) {
        console.error("Test failed:", error);
    } finally {
        await driver.quit();
    }
}

runLoginTest();
