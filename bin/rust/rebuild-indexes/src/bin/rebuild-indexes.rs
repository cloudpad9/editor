use std::env;
use std::fs;
use std::collections::HashSet;
use serde::{Serialize, Deserialize};
use std::time::Instant;

#[derive(Serialize, Deserialize)]
struct FileManager {
    excluded_patterns: HashSet<String>,
}

impl FileManager {
    fn new() -> Self {
        let excluded_patterns: HashSet<String> = ["/node_modules", "/vendor", "/.", "/tmp"].iter().map(|s| s.to_string()).collect();
        FileManager { excluded_patterns }
    }

    fn index(&self, dirs: Vec<&str>, cachefile: &str) {
        let start_time = Instant::now(); // Bắt đầu đo thời gian

        let filepaths: Vec<String> = dirs
            .iter()
            .flat_map(|dir| self.rsearch(dir, &vec![], &vec![]))
            .collect();

        let php_array = format!(
            "<?php\nreturn {};\n",
            serde_json::to_string(&filepaths).expect("Failed to serialize file paths")
        );

        fs::write(cachefile, php_array).expect("Failed to write cache file");

        let end_time = Instant::now(); // Kết thúc đo thời gian

        let elapsed_time = end_time - start_time; // Tính thời gian trôi qua

        println!("Elapsed time: {} milliseconds", elapsed_time.as_millis());
    }

    fn rsearch(&self, dir: &str, _excludes: &[&str], _includes: &[&str]) -> Vec<String> {
        let mut dirs = vec![dir.to_string()];
        let mut filepaths = Vec::new();

        while let Some(current_dir) = dirs.pop() {
            if let Ok(tree) = fs::read_dir(&current_dir) {
                for entry in tree {
                    if let Ok(file) = entry {
                        let file_path = file.path();
                        let file_path_str = file_path.to_str().unwrap_or("");

                        if file_path.is_dir() && self.is_excluded_path(file_path_str, _excludes, _includes) {
                            continue;
                        }

                        if file_path.is_dir() && file_path.file_name() != Some("cache".as_ref()) {
                            dirs.push(file_path_str.to_string());
                        } else if file_path.is_file() {
                            filepaths.push(file_path_str.to_string());
                        }
                    }
                }
            }
        }

        filepaths
    }

    fn is_excluded_path(&self, path: &str, _excludes: &[&str], _includes: &[&str]) -> bool {
        self.excluded_patterns.iter().any(|pattern| path.contains(pattern))
    }
}

fn main() {
    let args: Vec<String> = env::args().collect();

    if args.len() < 3 {
        println!("Usage: {} <dir1> <dir2> ... <outfile>", args[0]);
        std::process::exit(1);
    }

    let file_manager = FileManager::new();

    let dirs: Vec<&str> = args[1..args.len() - 1].iter().map(|s| s.as_str()).collect();
    let cachefile = &args[args.len() - 1];

    file_manager.index(dirs, cachefile);
}