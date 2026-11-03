@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM ============================================================================
REM ExFace Multi-Repository Release Script
REM
REM Usage:
REM     release-apps.bat NEW_VERSION PREVIOUS_RELEASE [--dry-run]
REM
REM Examples:
REM     release-apps.bat 1.42 1.41 --dry-run
REM     release-apps.bat 1.42 1.41
REM
REM Run from the installation folder - e.g.:
REM     C:\wamp\www\exface\exface
REM
REM Dry-run behavior:
REM     - Checks for tracked, staged, deleted, and untracked files
REM     - Fetches remote branches and tags
REM     - Checks whether the release tag already exists
REM     - Checks whether the GitHub Release already exists
REM     - Compares origin/master and origin/1.x-dev (informational only)
REM     - Tests checkout of master and 1.x-dev
REM     - Does NOT reset or move any branch
REM     - Does NOT create or move the release backup branch
REM     - Does NOT create tags
REM     - Does NOT push anything
REM     - Does NOT create GitHub Releases
REM     - Leaves the repository on 1.x-dev after a successful check
REM
REM Live behavior:
REM     - Synchronizes local master with origin/master
REM     - Creates release/PREVIOUS_RELEASE from origin/master
REM     - Pushes the backup branch
REM     - Synchronizes master with origin/1.x-dev
REM     - Pushes master
REM     - Creates and pushes the new tag
REM     - Creates the GitHub Release
REM     - Returns to 1.x-dev and updates it only when a fast-forward is possible
REM
REM Note:
REM     - A release is always created, even if master and 1.x-dev are identical.
REM     - Duplicate releases are prevented by the tag and release existence checks.
REM     - An existing release/PREVIOUS_RELEASE branch is moved to origin/master;
REM       its previous position is not preserved. So if master is on 1.42.02 and the
REM       1.43 is created, there will a new 1.42 branch, that remains on 1.42.02
REM       beause it is branched from master.
REM     - Merge patches from master into 1.x-dev before releasing. Patches present
REM       only on master are excluded from the new master and release, but remain
REM       in the backup branch. The script does not merge or check for such patches,
REM       including during a dry run.
REM     - Unpushed commits on local 1.x-dev are preserved and are not released.
REM       If local 1.x-dev is ahead of or diverged from origin/1.x-dev, it is left
REM       unchanged with a warning. Only pushed commits are included in the release.
REM
REM Prerequisites:
REM     git
REM     gh
REM     gh auth login
REM ============================================================================


REM ============================================================================
REM Arguments
REM ============================================================================

if "%~1"=="" goto :usage
if "%~2"=="" goto :usage

set "VERSION=%~1"
set "PREV_RELEASE=%~2"
set "ROOT=%CD%"
set "DRYRUN=0"

if not "%~3"=="" (
    if /I "%~3"=="--dry-run" (
        set "DRYRUN=1"
    ) else (
        echo ERROR: Unknown third parameter "%~3"
        goto :usage
    )
)


REM ============================================================================
REM Summary variables
REM ============================================================================

set /a TOTAL_COUNT=0
set /a SUCCESS_COUNT=0
set /a SKIPPED_COUNT=0
set /a ERROR_COUNT=0

set /a SUCCESS_INDEX=0
set /a SKIPPED_INDEX=0
set /a ERROR_INDEX=0


REM ============================================================================
REM Global prerequisite checks
REM ============================================================================

where git >nul 2>nul

if errorlevel 1 (
    echo ERROR: Git was not found in PATH.
    exit /b 1
)

where gh >nul 2>nul

if errorlevel 1 (
    echo ERROR: GitHub CLI "gh" was not found in PATH.
    echo.
    echo Install it with:
    echo     winget install --id GitHub.cli --source winget
    exit /b 1
)

gh auth status >nul 2>nul

if errorlevel 1 (
    echo ERROR: GitHub CLI is not authenticated.
    echo.
    echo Run:
    echo     gh auth login
    exit /b 1
)


REM ============================================================================
REM Header
REM ============================================================================

echo ==========================================================
echo ExFace Release Script
echo ==========================================================
echo New version      : %VERSION%
echo Previous release : %PREV_RELEASE%
echo Backup branch    : release/%PREV_RELEASE%
echo Root directory   : %ROOT%

if "%DRYRUN%"=="1" (
    echo Mode             : DRY RUN
) else (
    echo Mode             : LIVE
)

echo ==========================================================
echo.


REM ============================================================================
REM Repository list
REM ============================================================================

call :releaseRepo "vendor\exface\core" "exface/Core"
call :releaseRepo "vendor\exface\ui5facade" "exface/UI5Facade"
call :releaseRepo "vendor\exface\jeasyuifacade" "exface/JEasyUIFacade"
call :releaseRepo "vendor\exface\barcodescanner" "exface/BarcodeScanner"
call :releaseRepo "vendor\exface\urldataconnector" "exface/UrlDataConnector"
call :releaseRepo "vendor\exface\sapconnector" "exface/SapConnector"

call :releaseRepo "vendor\axenox\etl" "axenox/ETL"
call :releaseRepo "vendor\axenox\flysystemconnector" "axenox/FlysystemConnector"
call :releaseRepo "vendor\axenox\IDE" "axenox/IDE"
call :releaseRepo "vendor\axenox\microsoft365Connector" "axenox/Microsoft365Connector"
call :releaseRepo "vendor\axenox\notifier" "axenox/Notifier"
call :releaseRepo "vendor\axenox\oauth2connector" "axenox/OAuth2Connector"
call :releaseRepo "vendor\axenox\packagemanager" "axenox/PackageManager"
call :releaseRepo "vendor\axenox\pdfprinter" "axenox/PDFPrinter"
call :releaseRepo "vendor\axenox\surveyprinter" "axenox/SurveyPrinter"


REM ============================================================================
REM Final summary
REM ============================================================================

cd /d "%ROOT%"

echo.
echo ==========================================================
echo RELEASE SUMMARY
echo ==========================================================
echo Total repositories : %TOTAL_COUNT%
echo Successful         : %SUCCESS_COUNT%
echo Skipped            : %SKIPPED_COUNT%
echo Errors             : %ERROR_COUNT%
echo.

if %SUCCESS_COUNT% GTR 0 (
    echo Successful:
    for /L %%I in (1,1,%SUCCESS_INDEX%) do (
        echo   [OK] !SUCCESS_ITEM[%%I]!
    )
    echo.
)

if %SKIPPED_COUNT% GTR 0 (
    echo Skipped:
    for /L %%I in (1,1,%SKIPPED_INDEX%) do (
        echo   [SKIPPED] !SKIPPED_ITEM[%%I]!
    )
    echo.
)

if %ERROR_COUNT% GTR 0 (
    echo Errors:
    for /L %%I in (1,1,%ERROR_INDEX%) do (
        echo   [ERROR] !ERROR_ITEM[%%I]!
    )
    echo.
)

if "%DRYRUN%"=="1" (
    echo NOTE: This was a dry run.
    echo No branches were reset or moved.
    echo No remote branches, tags, or GitHub Releases were changed.
    echo Repositories that passed were left on 1.x-dev.
    echo.
)

echo ==========================================================

if %ERROR_COUNT% GTR 0 (
    exit /b 1
)

exit /b 0


REM ============================================================================
REM Process one repository
REM ============================================================================

:releaseRepo

set "REPO_DIR=%~1"
set "REPO_NAME=%~2"
set /a TOTAL_COUNT+=1

echo.
echo ==========================================================
echo %REPO_NAME%
echo ==========================================================


REM --------------------------------------------------------------------------
REM Verify repository directory
REM --------------------------------------------------------------------------

if not exist "%ROOT%\%REPO_DIR%\.git" (
    echo ERROR: Git repository not found:
    echo        %ROOT%\%REPO_DIR%

    call :recordError "%REPO_NAME% - repository directory not found"
    goto :eof
)

cd /d "%ROOT%\%REPO_DIR%"

if errorlevel 1 (
    echo ERROR: Cannot enter repository directory.

    call :recordError "%REPO_NAME% - cannot enter repository directory"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Verify Git working tree
REM --------------------------------------------------------------------------

git rev-parse --is-inside-work-tree >nul 2>nul

if errorlevel 1 (
    echo ERROR: Directory is not a valid Git working tree.

    call :recordError "%REPO_NAME% - invalid Git repository"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Check for all local changes
REM
REM This includes:
REM     - modified tracked files
REM     - staged files
REM     - deleted files
REM     - renamed files
REM     - untracked files
REM --------------------------------------------------------------------------

echo Checking working tree ...

set "WORKTREE_DIRTY=0"

for /f "delims=" %%S in ('git status --porcelain 2^>nul') do (
    set "WORKTREE_DIRTY=1"
)

if "!WORKTREE_DIRTY!"=="1" (
    echo ERROR: Repository contains local changes:
    echo.

    git status --short

    echo.
    call :recordError "%REPO_NAME% - local changes"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Detect detached HEAD
REM --------------------------------------------------------------------------

set "CURRENT_BRANCH="

for /f "delims=" %%B in ('git branch --show-current 2^>nul') do (
    set "CURRENT_BRANCH=%%B"
)

if not defined CURRENT_BRANCH (
    echo ERROR: Repository is in detached HEAD state.

    call :recordError "%REPO_NAME% - detached HEAD"
    goto :eof
)

echo Current branch: !CURRENT_BRANCH!


REM --------------------------------------------------------------------------
REM Fetch current remote state
REM --------------------------------------------------------------------------

echo Fetching origin ...

git fetch origin --prune --tags

if errorlevel 1 (
    echo ERROR: Fetch from origin failed.

    call :recordError "%REPO_NAME% - fetch failed"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Verify remote branches
REM --------------------------------------------------------------------------

git rev-parse --verify origin/master >nul 2>nul

if errorlevel 1 (
    echo ERROR: Remote branch origin/master does not exist.

    call :recordError "%REPO_NAME% - origin/master missing"
    goto :eof
)

git rev-parse --verify origin/1.x-dev >nul 2>nul

if errorlevel 1 (
    echo ERROR: Remote branch origin/1.x-dev does not exist.

    call :recordError "%REPO_NAME% - origin/1.x-dev missing"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Verify local branches
REM --------------------------------------------------------------------------

git show-ref --verify --quiet refs/heads/master

if errorlevel 1 (
    echo ERROR: Local branch master does not exist.
    echo Create it with:
    echo     git branch master origin/master

    call :recordError "%REPO_NAME% - local master missing"
    goto :eof
)

git show-ref --verify --quiet refs/heads/1.x-dev

if errorlevel 1 (
    echo ERROR: Local branch 1.x-dev does not exist.
    echo Create it with:
    echo     git branch 1.x-dev origin/1.x-dev

    call :recordError "%REPO_NAME% - local 1.x-dev missing"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Check whether release tag already exists remotely
REM --------------------------------------------------------------------------

echo Checking remote tag %VERSION% ...

git ls-remote --exit-code --tags origin "refs/tags/%VERSION%" >nul 2>nul

if not errorlevel 1 (
    echo SKIPPED: Remote tag %VERSION% already exists.

    call :recordSkipped "%REPO_NAME% - tag %VERSION% already exists"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Check whether GitHub Release already exists
REM --------------------------------------------------------------------------

echo Checking GitHub Release %VERSION% ...

gh release view "%VERSION%" >nul 2>nul

if not errorlevel 1 (
    echo SKIPPED: GitHub Release %VERSION% already exists.

    call :recordSkipped "%REPO_NAME% - GitHub Release %VERSION% already exists"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Compare master and 1.x-dev (informational only).
REM A release is always created, even if the branches are identical.
REM --------------------------------------------------------------------------

echo Comparing origin/master with origin/1.x-dev ...

git diff --quiet origin/master origin/1.x-dev

if not errorlevel 1 (
    echo NOTE: master and 1.x-dev are identical. A release will still be created.
) else (
    echo master and 1.x-dev differ. Proceeding with release.
)


REM --------------------------------------------------------------------------
REM Show commits from 1.x-dev that are not reachable from master
REM --------------------------------------------------------------------------

echo.
echo Commits in origin/1.x-dev not reachable from origin/master:
echo ----------------------------------------------------------

for /f "delims=" %%C in ('git log --oneline --no-decorate origin/master..origin/1.x-dev 2^>nul') do (
    echo %%C
)

set "NEW_COMMITS=0"
for /f %%N in ('git rev-list --count origin/master..origin/1.x-dev 2^>nul') do set "NEW_COMMITS=%%N"

if "!NEW_COMMITS!"=="0" (
    echo (none - branches are identical, release created anyway)
)

echo ----------------------------------------------------------
echo.


REM --------------------------------------------------------------------------
REM Test checkout of master
REM
REM This runs in both dry-run and live mode.
REM Since the working tree was checked above, the checkout should be safe.
REM --------------------------------------------------------------------------

echo Checking out master ...

git checkout master

if errorlevel 1 (
    echo ERROR: Could not check out master.

    call :recordError "%REPO_NAME% - checkout master failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Dry-run validation
REM
REM Do not reset or move any local branch.
REM --------------------------------------------------------------------------

if "%DRYRUN%"=="1" (
    echo.
    echo Local master:
    git log -1 --oneline master

    echo Remote master:
    git log -1 --oneline origin/master

    echo Remote 1.x-dev:
    git log -1 --oneline origin/1.x-dev

    echo.
    echo [DRY-RUN] Would run:
    echo [DRY-RUN] git reset --hard origin/master
    echo [DRY-RUN] git branch -f release/%PREV_RELEASE% origin/master
    echo [DRY-RUN] git push origin release/%PREV_RELEASE% --force-with-lease
    echo [DRY-RUN] git reset --hard origin/1.x-dev
    echo [DRY-RUN] git push origin master --force-with-lease
    echo [DRY-RUN] git tag -a %VERSION% -m "Release %VERSION%"
    echo [DRY-RUN] git push origin %VERSION%
    echo [DRY-RUN] gh release create %VERSION% --title "%VERSION%" --notes "See https://github.com/exface/Core/releases/tag/%VERSION%"
    echo.

    echo Testing checkout of 1.x-dev ...

    git checkout 1.x-dev

    if errorlevel 1 (
        echo ERROR: Could not check out 1.x-dev.

        call :recordError "%REPO_NAME% - checkout 1.x-dev failed"
        goto :eof
    )

    echo Local 1.x-dev:
    git log -1 --oneline 1.x-dev

    echo Remote 1.x-dev:
    git log -1 --oneline origin/1.x-dev

    call :updateDevelopmentBranch

    if errorlevel 1 (
        call :recordError "%REPO_NAME% - check update 1.x-dev failed"
        goto :eof
    )

    call :recordSuccess "%REPO_NAME% - dry run passed"
    echo SUCCESS: Dry-run checks passed for %REPO_NAME%.
    goto :eof
)


REM ============================================================================
REM Live release operations
REM ============================================================================


REM --------------------------------------------------------------------------
REM Update local master from current remote master
REM --------------------------------------------------------------------------

echo Updating local master from origin/master ...

git reset --hard origin/master

if errorlevel 1 (
    echo ERROR: Could not update local master from origin/master.

    call :recordError "%REPO_NAME% - update master failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Create backup branch from origin/master
REM --------------------------------------------------------------------------

echo Creating backup branch release/%PREV_RELEASE% from origin/master ...

git branch -f "release/%PREV_RELEASE%" origin/master

if errorlevel 1 (
    echo ERROR: Could not create local backup branch.

    call :recordError "%REPO_NAME% - backup branch creation failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Push backup branch
REM --------------------------------------------------------------------------

echo Pushing backup branch release/%PREV_RELEASE% ...

git push origin "release/%PREV_RELEASE%" --force-with-lease

if errorlevel 1 (
    echo ERROR: Could not push backup branch release/%PREV_RELEASE%.

    call :recordError "%REPO_NAME% - backup branch push failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Synchronize master with origin/1.x-dev
REM --------------------------------------------------------------------------

echo Synchronizing master with origin/1.x-dev ...

git reset --hard origin/1.x-dev

if errorlevel 1 (
    echo ERROR: Could not reset master to origin/1.x-dev.

    call :recordError "%REPO_NAME% - master reset failed"
    goto :restoreLocalMaster
)


REM --------------------------------------------------------------------------
REM Push master
REM --------------------------------------------------------------------------

echo Pushing master ...

git push origin master --force-with-lease

if errorlevel 1 (
    echo ERROR: Could not push master.

    call :recordError "%REPO_NAME% - master push failed"
    goto :restoreLocalMaster
)


REM --------------------------------------------------------------------------
REM Create annotated release tag
REM --------------------------------------------------------------------------

echo Creating tag %VERSION% ...

git tag -d "%VERSION%" >nul 2>nul

git tag -a "%VERSION%" -m "Release %VERSION%"

if errorlevel 1 (
    echo ERROR: Could not create release tag.

    call :recordError "%REPO_NAME% - tag creation failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Push tag
REM --------------------------------------------------------------------------

echo Pushing tag %VERSION% ...

git push origin "%VERSION%"

if errorlevel 1 (
    echo ERROR: Could not push release tag.

    call :recordError "%REPO_NAME% - tag push failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Create GitHub Release
REM --------------------------------------------------------------------------

echo Creating GitHub Release ...

gh release create "%VERSION%" ^
    --title "%VERSION%" ^
    --notes "See https://github.com/exface/Core/releases/tag/%VERSION%"

if errorlevel 1 (
    echo ERROR: The tag was pushed, but GitHub Release creation failed.
    echo.
    echo Manual recovery command:
    echo gh release create "%VERSION%" --title "%VERSION%" --notes "See https://github.com/exface/Core/releases/tag/%VERSION%"
    echo.

    call :recordError "%REPO_NAME% - GitHub Release creation failed"
    goto :switchToDevelopmentBranch
)


REM --------------------------------------------------------------------------
REM Switch to and update 1.x-dev
REM --------------------------------------------------------------------------

echo Switching back to 1.x-dev ...

git checkout 1.x-dev

if errorlevel 1 (
    echo ERROR: Release succeeded, but checkout of 1.x-dev failed.

    call :recordError "%REPO_NAME% - released, but checkout 1.x-dev failed"
    goto :eof
)

call :updateDevelopmentBranch

if errorlevel 1 (
    echo ERROR: Release succeeded, but local 1.x-dev could not be updated.

    call :recordError "%REPO_NAME% - released, but update 1.x-dev failed"
    goto :eof
)


REM --------------------------------------------------------------------------
REM Success
REM --------------------------------------------------------------------------

call :recordSuccess "%REPO_NAME% %VERSION%"

echo SUCCESS: Released %REPO_NAME% %VERSION%.
goto :eof


REM ============================================================================
REM Recovery: restore local master after a failed master push/reset
REM ============================================================================

:restoreLocalMaster

echo Restoring local master to origin/master ...

git reset --hard origin/master >nul 2>nul

goto :switchToDevelopmentBranch


REM ============================================================================
REM Update the checked-out 1.x-dev only when no local commits would be lost.
REM Dry runs report the same decision without moving the branch.
REM ============================================================================

:updateDevelopmentBranch

git merge-base --is-ancestor 1.x-dev origin/1.x-dev

if errorlevel 2 (
    echo ERROR: Could not compare local 1.x-dev with origin/1.x-dev.
    exit /b 1
)

if errorlevel 1 (
    echo WARNING: Local 1.x-dev has unpushed commits; leaving it unchanged.
    echo Only origin/1.x-dev is included in the release. No local commits are pushed.
    exit /b 0
)

if "%DRYRUN%"=="1" (
    echo [DRY-RUN] Would run: git merge --ff-only --no-edit origin/1.x-dev
    exit /b 0
)

echo Updating local 1.x-dev from origin/1.x-dev with fast-forward only ...

git merge --ff-only --no-edit origin/1.x-dev

if errorlevel 1 (
    echo ERROR: Could not fast-forward local 1.x-dev.
    exit /b 1
)

exit /b 0


REM ============================================================================
REM Switch to 1.x-dev
REM
REM In dry-run or skipped cases this only checks out the branch.
REM It does not reset it.
REM ============================================================================

:switchToDevelopmentBranch

echo Switching to 1.x-dev ...

git checkout 1.x-dev >nul 2>nul

if errorlevel 1 (
    echo WARNING: Could not switch to 1.x-dev.
)

goto :eof


REM ============================================================================
REM Summary helpers
REM ============================================================================

:recordSuccess

set /a SUCCESS_COUNT+=1
set /a SUCCESS_INDEX+=1
set "SUCCESS_ITEM[!SUCCESS_INDEX!]=%~1"

exit /b 0


:recordSkipped

set /a SKIPPED_COUNT+=1
set /a SKIPPED_INDEX+=1
set "SKIPPED_ITEM[!SKIPPED_INDEX!]=%~1"

exit /b 0


:recordError

set /a ERROR_COUNT+=1
set /a ERROR_INDEX+=1
set "ERROR_ITEM[!ERROR_INDEX!]=%~1"

exit /b 0


REM ============================================================================
REM Usage
REM ============================================================================

:usage

echo.
echo Usage:
echo.
echo     release-apps.bat NEW_VERSION PREVIOUS_RELEASE [--dry-run]
echo.
echo Examples:
echo.
echo     release-apps.bat 1.42 1.41 --dry-run
echo     release-apps.bat 1.42 1.41
echo.

exit /b 1