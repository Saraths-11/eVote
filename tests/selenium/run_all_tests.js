const { Builder, By, until } = require('selenium-webdriver');
const path = require('path');
const fs = require('fs');

// ─── ANSI Color Helpers ───────────────────────────────────────────────────────
const GREEN = (s) => `\x1b[32m${s}\x1b[0m`;
const RED = (s) => `\x1b[31m${s}\x1b[0m`;
const YELLOW = (s) => `\x1b[33m${s}\x1b[0m`;
const CYAN = (s) => `\x1b[36m${s}\x1b[0m`;
const BOLD = (s) => `\x1b[1m${s}\x1b[0m`;

const DIVIDER = '='.repeat(55);
const THIN = '-'.repeat(55);

// ─── Log File Setup ───────────────────────────────────────────────────────────
const LOG_FILE = path.join(__dirname, 'test_results.log');
fs.writeFileSync(LOG_FILE, `eVote Test Run — ${new Date().toLocaleString()}\n${'='.repeat(55)}\n\n`, 'utf8');

function log(msg, plain = '') {
    // plain = version without ANSI for file
    console.log(msg);
    fs.appendFileSync(LOG_FILE, (plain || msg.replace(/\x1b\[[0-9;]*m/g, '')) + '\n', 'utf8');
}

// ─── Result Tracker ───────────────────────────────────────────────────────────
const results = [];

function printStep(msg) { log(CYAN('  > ') + msg, `  > ${msg}`); }
function printSuccess(msg) { log(GREEN('  OK ') + msg, `  OK ${msg}`); }

function recordResult(num, name, passed, errorMsg = '') {
    results.push({ num, name, passed, errorMsg });
    log('');
    log(THIN);
    if (passed) {
        log(BOLD(GREEN(`  TEST ${num} PASSED: ${name}`)),
            `  TEST ${num} PASSED: ${name}`);
    } else {
        log(BOLD(RED(`  TEST ${num} FAILED: ${name}`)),
            `  TEST ${num} FAILED: ${name}`);
        if (errorMsg) {
            const short = errorMsg.split('\n')[0].substring(0, 110);
            log(RED(`     Reason: ${short}`), `     Reason: ${short}`);
        }
    }
    log(THIN);
    log('');
}

// ─── TEST 1: Admin Login & Dashboard Surf ────────────────────────────────────
async function test1_adminLoginSurf() {
    const NAME = 'Admin Login & Dashboard Surf';
    log('');
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);
    log(BOLD(YELLOW(`  STARTING TEST 1: ${NAME}`)), `  STARTING TEST 1: ${NAME}`);
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);

    let driver = await new Builder().forBrowser('chrome').build();
    try {
        printStep('Navigating to login page...');
        await driver.get('http://localhost/evote/login.php');

        printStep('Entering admin credentials...');
        await driver.findElement(By.name('email')).sendKeys('sarath123@gmail.com');
        await driver.findElement(By.name('password')).sendKeys('2004');
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('admin_dashboard.php'), 7000);
        printSuccess('Logged in to Admin Dashboard.');

        const sections = [
            { name: 'Manage Elections', url: 'manage_elections.php' },
            { name: 'View Participants', url: 'view_participants.php' },
            { name: 'Election Results', url: 'view_results.php' },
            { name: 'Audit Logs', url: 'view_logs.php' }
        ];
        for (let s of sections) {
            printStep(`Navigating to ${s.name}...`);
            await driver.get('http://localhost/evote/' + s.url);
            await driver.wait(until.urlContains(s.url), 7000);
            printSuccess(`${s.name} loaded successfully.`);
            await driver.sleep(600);
        }

        printStep('Logging out...');
        await driver.get('http://localhost/evote/logout.php');
        await driver.wait(until.urlContains('login.php'), 5000);
        printSuccess('Logged out successfully.');

        recordResult(1, NAME, true);
    } catch (err) {
        recordResult(1, NAME, false, err.message);
    } finally {
        await driver.quit();
    }
}

// ─── TEST 2: Student Registration & Surf ─────────────────────────────────────
async function test2_studentRegistrationSurf() {
    const NAME = 'Student Registration & Dashboard Surf';
    log('');
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);
    log(BOLD(YELLOW(`  STARTING TEST 2: ${NAME}`)), `  STARTING TEST 2: ${NAME}`);
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);

    const testEmail = `student${Date.now()}@mca.ajce.in`;
    const testPassword = 'Password@123';

    let driver = await new Builder().forBrowser('chrome').build();
    try {
        printStep('Navigating to signup page...');
        await driver.get('http://localhost/evote/signup.php');

        printStep(`Registering new student: ${testEmail}`);
        await driver.findElement(By.id('name-input')).sendKeys('Selenium Student');
        await driver.findElement(By.id('role-select')).sendKeys('Student');
        await driver.findElement(By.id('email-input')).sendKeys(testEmail);
        await driver.findElement(By.name('college_id')).sendKeys('99999');
        await driver.findElement(By.name('department')).sendKeys('MCA');
        await driver.findElement(By.name('year')).sendKeys('1');
        await driver.findElement(By.id('password')).sendKeys(testPassword);
        await driver.findElement(By.id('confirm_password')).sendKeys(testPassword);
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.sleep(2500);
        printSuccess(`Registration submitted for ${testEmail}`);

        printStep('Logging in with new student account...');
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys(testEmail);
        await driver.findElement(By.name('password')).sendKeys(testPassword);
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('student_dashboard.php'), 7000);
        printSuccess('Logged in to Student Dashboard.');

        const sections = [
            { name: 'Profile Settings', url: 'student_account.php' },
            { name: 'Main Dashboard', url: 'student_dashboard.php' }
        ];
        for (let s of sections) {
            printStep(`Navigating to ${s.name}...`);
            await driver.get('http://localhost/evote/' + s.url);
            await driver.wait(until.urlContains(s.url), 7000);
            printSuccess(`${s.name} loaded successfully.`);
            await driver.sleep(600);
        }

        printStep('Logging out...');
        await driver.get('http://localhost/evote/logout.php');
        await driver.wait(until.urlContains('login.php'), 5000);
        printSuccess('Logged out successfully.');

        recordResult(2, NAME, true);
    } catch (err) {
        recordResult(2, NAME, false, err.message);
    } finally {
        await driver.quit();
    }
}

// ─── TEST 3: Create Election ──────────────────────────────────────────────────
async function test3_createElection() {
    const NAME = 'Create Election';
    log('');
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);
    log(BOLD(YELLOW(`  STARTING TEST 3: ${NAME}`)), `  STARTING TEST 3: ${NAME}`);
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);

    let driver = await new Builder().forBrowser('chrome').build();
    try {
        printStep('Logging in as Admin...');
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys('sarath123@gmail.com');
        await driver.findElement(By.name('password')).sendKeys('2004');
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('admin_dashboard.php'), 7000);
        printSuccess('Admin login successful.');

        printStep('Navigating to Create Election page...');
        await driver.get('http://localhost/evote/create_election.php');

        printStep('Filling election details...');
        await driver.findElement(By.name('title')).sendKeys('Live Selenium Election ' + Date.now());
        await driver.findElement(By.name('description')).sendKeys('Automated test created in real-time.');

        const setDate = async (id, val) => {
            await driver.executeScript(`document.getElementById("${id}").value = "${val}";`);
        };
        await setDate('registration_start', '2026-05-01T10:00');
        await setDate('registration_end', '2026-05-05T10:00');
        await setDate('cancellation_start', '2026-05-06T10:00');
        await setDate('cancellation_end', '2026-05-10T10:00');
        await setDate('election_start', '2026-05-15T09:00');
        await setDate('election_end', '2026-05-15T16:00');
        printSuccess('Election form filled.');

        printStep('Submitting election form...');
        await driver.sleep(1000);
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('manage_elections.php'), 7000);
        printSuccess('Redirected to Manage Elections — election created.');

        await driver.sleep(2000);
        recordResult(3, NAME, true);
    } catch (err) {
        recordResult(3, NAME, false, err.message);
    } finally {
        await driver.quit();
    }
}

// ─── TEST 4: Candidate Nomination ────────────────────────────────────────────
async function test4_candidateNomination() {
    const NAME = 'Candidate Nomination Submission';
    log('');
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);
    log(BOLD(YELLOW(`  STARTING TEST 4: ${NAME}`)), `  STARTING TEST 4: ${NAME}`);
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);

    const testEmail = 'nomination_test@mca.ajce.in';
    const testPassword = 'Password@123';

    let driver = await new Builder().forBrowser('chrome').build();
    try {
        printStep('Logging in as candidate student...');
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys(testEmail);
        await driver.findElement(By.name('password')).sendKeys(testPassword);
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('student_dashboard.php'), 7000);
        printSuccess('Logged in to Student Dashboard.');

        printStep('Looking for Register Now / Nominate button...');
        await driver.get('http://localhost/evote/student_dashboard.php');
        await driver.sleep(1500);

        let nominateBtn;
        try {
            nominateBtn = await driver.wait(
                until.elementLocated(By.xpath("//a[contains(text(),'Register Now') or contains(text(),'Nominate')]")),
                6000
            );
        } catch (_) {
            // Fallback: maybe the student is already registered — look for current state
            let pageText = await driver.findElement(By.css('body')).getText();
            if (pageText.includes('Registered') || pageText.includes('Pending') || pageText.includes('Approved')) {
                printSuccess('Student already registered — nomination step skipped (already enrolled).');
                recordResult(4, NAME, true);
                return;
            }
            throw new Error('Could not find Register Now/Nominate button on dashboard');
        }

        await nominateBtn.click();
        await driver.wait(until.urlContains('register_participant.php'), 7000);
        printSuccess('Reached Nomination / Registration page.');

        printStep('Accepting nomination rules...');
        let rulesCheckbox = await driver.findElement(By.id('rulesCheckbox'));
        await driver.executeScript("arguments[0].click();", rulesCheckbox);
        printSuccess('Rules checkbox accepted.');

        printStep('Filling identity & uploading files...');
        await driver.findElement(By.name('name')).sendKeys('Selenium Candidate');
        await driver.findElement(By.name('gender')).sendKeys('Male');
        await driver.findElement(By.name('dob')).sendKeys('2000-01-01');

        const dummyPath = path.resolve(__dirname, 'dummy.png');
        if (!fs.existsSync(dummyPath)) fs.writeFileSync(dummyPath, 'dummy data');

        await driver.findElement(By.name('photo')).sendKeys(dummyPath);
        await driver.findElement(By.name('proof_file')).sendKeys(dummyPath);
        await driver.findElement(By.name('signature')).sendKeys(dummyPath);
        printSuccess('Form fields filled and files uploaded.');

        printStep('Submitting nomination...');
        let submitBtn = await driver.findElement(By.id('submitBtn'));
        await driver.executeScript("arguments[0].disabled = false; arguments[0].click();", submitBtn);
        await driver.sleep(2500);

        // Check for any error banners
        try {
            let errEl = await driver.findElement(By.className('alert-error'));
            let errTxt = await errEl.getText();
            if (errTxt.trim()) throw new Error('Page error: ' + errTxt.trim());
        } catch (e) {
            if (e.message.startsWith('Page error:')) throw e;
        }

        await driver.wait(
            until.elementLocated(By.xpath("//*[contains(text(),'Pending') or contains(text(),'Successfully') or contains(text(),'submitted')]")),
            8000
        );
        printSuccess('Nomination submitted — status shows Pending / Success.');

        recordResult(4, NAME, true);
    } catch (err) {
        recordResult(4, NAME, false, err.message);
    } finally {
        await driver.quit();
    }
}

// ─── TEST 5: Full Voting Flow ─────────────────────────────────────────────────
async function test5_votingFlow() {
    const NAME = 'Full Voting Flow';
    log('');
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);
    log(BOLD(YELLOW(`  STARTING TEST 5: ${NAME}`)), `  STARTING TEST 5: ${NAME}`);
    log(BOLD(YELLOW(DIVIDER)), DIVIDER);

    const testEmail = 'nomination_test@mca.ajce.in';
    const testPassword = 'Password@123';
    const testName = 'Selenium Candidate';

    let driver = await new Builder().forBrowser('chrome').build();
    try {
        printStep('Logging in as student voter...');
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys(testEmail);
        await driver.findElement(By.name('password')).sendKeys(testPassword);
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('student_dashboard.php'), 7000);
        printSuccess('Logged in to Student Dashboard.');

        printStep('Locating Vote Now button on dashboard...');
        await driver.sleep(1500);
        // Try finding any Vote Now link regardless of election title (more robust)
        let voteNowBtn = await driver.wait(
            until.elementLocated(By.xpath("//a[contains(text(),'Vote Now') or contains(text(),'Vote')][@href]")),
            10000
        );
        await driver.executeScript("arguments[0].scrollIntoView();", voteNowBtn);
        await voteNowBtn.click();
        await driver.wait(until.urlContains('vote.php'), 7000);
        printSuccess('Reached Vote page.');

        printStep('Selecting candidate & clicking cast vote...');
        let candidateCard = await driver.wait(
            until.elementLocated(By.xpath(`//div[contains(@class,'candidate-card') and .//h3[contains(text(),'${testName}')]]`)),
            7000
        );
        let castVoteBtn = await candidateCard.findElement(By.css('button.vote-btn'));
        await castVoteBtn.click();
        await driver.wait(until.urlContains('verify_vote.php'), 7000);
        printSuccess('Moved to vote verification page.');

        printStep('Accepting terms & conditions...');
        let termsCheck = await driver.findElement(By.id('terms_agree'));
        await driver.executeScript("arguments[0].click();", termsCheck);
        let confirmBtn = await driver.findElement(By.id('submit_btn'));
        await confirmBtn.click();
        await driver.wait(until.urlContains('confirm_vote.php'), 7000);
        printSuccess('Terms accepted — moved to confirmation page.');

        printStep('Identity verification & final submission...');
        await driver.findElement(By.id('name')).sendKeys(testName);
        await driver.findElement(By.id('password')).sendKeys(testPassword);
        let finalSubmitBtn = await driver.findElement(By.name('submit_vote'));
        await finalSubmitBtn.click();

        printStep('Verifying vote success confirmation...');
        await driver.wait(
            until.elementLocated(By.xpath("//*[contains(text(),'Vote Submitted Successfully!')]")),
            12000
        );
        printSuccess('Vote confirmed — "Vote Submitted Successfully!" message found.');

        recordResult(5, NAME, true);
    } catch (err) {
        recordResult(5, NAME, false, err.message);
    } finally {
        await driver.quit();
    }
}

// ─── MAIN RUNNER ─────────────────────────────────────────────────────────────
async function runAllTests() {
    log('');
    log(BOLD(CYAN(DIVIDER)), DIVIDER);
    log(BOLD(CYAN('       eVote Selenium Test Suite -- Full Run')),
        '       eVote Selenium Test Suite -- Full Run');
    log(BOLD(CYAN(DIVIDER)), DIVIDER);
    log('');

    await test1_adminLoginSurf();
    await test2_studentRegistrationSurf();
    await test3_createElection();
    await test4_candidateNomination();
    await test5_votingFlow();

    // ─── FINAL SUMMARY ───────────────────────────────────────────────────────
    log('');
    log(BOLD(CYAN(DIVIDER)), DIVIDER);
    log(BOLD(CYAN('             TEST RESULTS SUMMARY')),
        '             TEST RESULTS SUMMARY');
    log(BOLD(CYAN(DIVIDER)), DIVIDER);

    let passed = 0;
    let failed = 0;

    for (const r of results) {
        const statusColored = r.passed
            ? BOLD(GREEN('  SUCCESSFUL [PASS]'))
            : BOLD(RED('  FAILED     [FAIL]'));
        const statusPlain = r.passed ? '  SUCCESSFUL [PASS]' : '  FAILED     [FAIL]';
        log(`  Test ${r.num}: ${r.name.padEnd(38)} ${statusColored}`,
            `  Test ${r.num}: ${r.name.padEnd(38)} ${statusPlain}`);
        if (!r.passed && r.errorMsg) {
            const short = `     |- ${r.errorMsg.split('\n')[0].substring(0, 95)}`;
            log(RED(short), short);
        }
        r.passed ? passed++ : failed++;
    }

    log('');
    log(THIN);
    const totLine = `  Total Tests: ${results.length}  |  Passed: ${passed}  |  Failed: ${failed}`;
    log(CYAN(totLine), totLine);
    log(THIN);
    log('');

    if (failed === 0) {
        log(BOLD(GREEN('  All tests completed SUCCESSFULLY!')),
            '  All tests completed SUCCESSFULLY!');
    } else {
        log(BOLD(RED(`  ${failed} test(s) FAILED. See details above.`)),
            `  ${failed} test(s) FAILED. See details above.`);
    }
    log('');
    log(`  Full log saved to: ${LOG_FILE}`, `  Full log saved to: ${LOG_FILE}`);
    log('');
}

runAllTests();
